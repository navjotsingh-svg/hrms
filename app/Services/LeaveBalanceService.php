<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    public function __construct(private LeaveTypeService $leaveTypeService) {}

    public function yearForDate(string $date): int
    {
        return (int) \Carbon\Carbon::parse($date)->format('Y');
    }

    public function ensureBalancesForEmployee(Employee $employee, ?int $year = null): Collection
    {
        $year ??= (int) now()->format('Y');
        $types = $this->leaveTypeService->activeForEmployee($employee);

        foreach ($types as $type) {
            $this->ensureBalanceRow($employee, $type, $year);
        }

        return $this->balancesForEmployee($employee, $year);
    }

    public function balancesForEmployee(Employee $employee, ?int $year = null): Collection
    {
        $year ??= (int) now()->format('Y');
        $assignedIds = $this->leaveTypeService->activeForEmployee($employee)->pluck('id');

        if ($assignedIds->isEmpty()) {
            return collect();
        }

        return EmployeeLeaveBalance::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->whereIn('leave_type_id', $assignedIds)
            ->get()
            ->sortBy(fn (EmployeeLeaveBalance $balance) => $balance->leaveType?->sort_order ?? 0)
            ->values();
    }

    public function balanceForType(Employee $employee, int $leaveTypeId, ?int $year = null): EmployeeLeaveBalance
    {
        if (! $this->leaveTypeService->isAssignedToEmployee($employee, $leaveTypeId)) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['This leave type is not assigned to the employee.'],
            ]);
        }

        $year ??= (int) now()->format('Y');
        $type = LeaveType::query()
            ->where('company_id', $employee->company_id)
            ->findOrFail($leaveTypeId);

        return $this->ensureBalanceRow($employee, $type, $year);
    }

    public function hasEnoughBalance(EmployeeLeaveBalance $balance, float $days): bool
    {
        if ($balance->leaveType?->isUnlimitedLeave()) {
            return true;
        }

        return $balance->available() >= $days;
    }

    public function reserve(EmployeeLeaveBalance $balance, float $days): void
    {
        $balance->increment('pending', $days);
    }

    public function releasePending(EmployeeLeaveBalance $balance, float $days): void
    {
        $balance->decrement('pending', min($days, (float) $balance->pending));
    }

    public function confirmUsage(EmployeeLeaveBalance $balance, float $days): void
    {
        $balance->decrement('pending', min($days, (float) $balance->pending));
        $balance->increment('used', $days);
    }

    public function restoreUsage(EmployeeLeaveBalance $balance, float $days): void
    {
        $balance->decrement('used', min($days, (float) $balance->used));
    }

    public function updateUsed(EmployeeLeaveBalance $balance, float $used): EmployeeLeaveBalance
    {
        if ($used < 0) {
            throw ValidationException::withMessages([
                'used' => ['Used leave cannot be negative.'],
            ]);
        }

        $balance->update(['used' => $used]);

        return $balance->fresh()->load('leaveType');
    }

    public function grantCompOff(EmployeeLeaveBalance $balance, float $days): EmployeeLeaveBalance
    {
        if (! $balance->leaveType?->isCompOff()) {
            throw ValidationException::withMessages([
                'leave_type' => ['Comp off can only be granted for the Comp Off leave type.'],
            ]);
        }

        if ($days <= 0) {
            throw ValidationException::withMessages([
                'days' => ['Grant days must be greater than zero.'],
            ]);
        }

        $balance->increment('adjusted', $days);

        return $balance->fresh()->load('leaveType');
    }

    public function setCompOffCredit(EmployeeLeaveBalance $balance, float $adjusted): EmployeeLeaveBalance
    {
        if (! $balance->leaveType?->isCompOff()) {
            throw ValidationException::withMessages([
                'leave_type' => ['Comp off credit can only be set for the Comp Off leave type.'],
            ]);
        }

        if ($adjusted < 0) {
            throw ValidationException::withMessages([
                'adjusted' => ['Comp off credit cannot be negative.'],
            ]);
        }

        $balance->update(['adjusted' => $adjusted]);

        return $balance->fresh()->load('leaveType');
    }

    public function syncFullAllocationsForCompany(int $companyId, ?int $year = null): void
    {
        $year ??= (int) now()->format('Y');

        Employee::query()
            ->where('company_id', $companyId)
            ->each(fn (Employee $employee) => $this->ensureBalancesForEmployee($employee, $year));
    }

    public function belongsToCompany(EmployeeLeaveBalance $balance, int $companyId): bool
    {
        return (int) $balance->company_id === $companyId;
    }

    /** @return array<string, mixed> */
    public function companyOverview(int $companyId, int $year, array $filters = []): array
    {
        $leaveTypes = $this->leaveTypeService->activeForCompany($companyId);

        $query = Employee::query()
            ->where('company_id', $companyId)
            ->with(['department', 'leaveTypes'])
            ->orderBy('first_name')
            ->orderBy('last_name');

        $status = $filters['status'] ?? 'active';

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->where(function ($builder) use ($departmentId) {
                $builder
                    ->where('department_id', $departmentId)
                    ->orWhereHas('departments', fn ($relation) => $relation->where('departments.id', $departmentId));
            });
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        /** @var LengthAwarePaginator $employees */
        $employees = $query->paginate((int) ($filters['per_page'] ?? 25));

        foreach ($employees as $employee) {
            $this->ensureBalancesForEmployee($employee, $year);
        }

        $employeeIds = $employees->pluck('id');
        $leaveTypeIds = $leaveTypes->pluck('id');

        $balancesByEmployee = EmployeeLeaveBalance::query()
            ->with('leaveType')
            ->whereIn('employee_id', $employeeIds)
            ->where('year', $year)
            ->whereIn('leave_type_id', $leaveTypeIds)
            ->get()
            ->groupBy('employee_id');

        $employeeRows = collect($employees->items())->map(function (Employee $employee) use ($leaveTypes, $balancesByEmployee) {
            $assignedIds = $employee->leaveTypes->pluck('id')->all();
            $employeeBalances = $balancesByEmployee->get($employee->id, collect())->keyBy('leave_type_id');

            $cells = [];

            foreach ($leaveTypes as $type) {
                if (! in_array($type->id, $assignedIds, true)) {
                    $cells[(string) $type->id] = null;

                    continue;
                }

                $balance = $employeeBalances->get($type->id);

                if (! $balance) {
                    $cells[(string) $type->id] = null;

                    continue;
                }

                $cells[(string) $type->id] = $this->formatOverviewCell($balance, $type);
            }

            return [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation,
                'status' => $employee->status,
                'balances' => $cells,
            ];
        });

        return [
            'year' => $year,
            'leave_types' => $leaveTypes->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'quota_unit' => $type->quotaUnit(),
                'is_comp_off' => $type->isCompOff(),
            ])->values()->all(),
            'employees' => $employeeRows->values()->all(),
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function formatOverviewCell(EmployeeLeaveBalance $balance, LeaveType $type): array
    {
        $available = $balance->available();

        return [
            'id' => $balance->id,
            'available' => $available === PHP_FLOAT_MAX ? null : round($available, $type->usesHourQuota() ? 2 : 1),
            'allocated' => (float) $balance->allocated,
            'used' => (float) $balance->used,
            'pending' => (float) $balance->pending,
            'adjusted' => (float) $balance->adjusted,
            'unit' => $type->quotaUnit(),
            'is_comp_off' => $type->isCompOff(),
        ];
    }

    private function ensureBalanceRow(Employee $employee, LeaveType $type, int $year): EmployeeLeaveBalance
    {
        $allocated = $this->calculateAllocation($type);

        $balance = EmployeeLeaveBalance::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'year' => $year,
            ],
            [
                'company_id' => $employee->company_id,
                'allocated' => $allocated,
                'used' => 0,
                'pending' => 0,
                'adjusted' => 0,
            ]
        );

        if ($type->annual_quota !== null && (float) $balance->allocated !== $allocated) {
            $balance->update(['allocated' => $allocated]);
        }

        return $balance->load('leaveType');
    }

    private function calculateAllocation(LeaveType $type): float
    {
        if ($type->annual_quota === null) {
            return 0;
        }

        return (float) $type->annual_quota;
    }

    public function balanceUnit(LeaveType $type): string
    {
        return $type->quotaUnit();
    }
}
