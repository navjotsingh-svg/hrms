<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
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
        $types = $this->leaveTypeService->activeForCompany($employee->company_id);

        foreach ($types as $type) {
            $this->ensureBalanceRow($employee, $type, $year);
        }

        return $this->balancesForEmployee($employee, $year);
    }

    public function balancesForEmployee(Employee $employee, ?int $year = null): Collection
    {
        $year ??= (int) now()->format('Y');

        return EmployeeLeaveBalance::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->sortBy(fn (EmployeeLeaveBalance $balance) => $balance->leaveType?->sort_order ?? 0)
            ->values();
    }

    public function balanceForType(Employee $employee, int $leaveTypeId, ?int $year = null): EmployeeLeaveBalance
    {
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
