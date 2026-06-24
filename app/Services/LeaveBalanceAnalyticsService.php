<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LeaveBalanceAnalyticsService
{
    public function __construct(private LeaveBalanceService $leaveBalanceService) {}

    public function report(User $user, array $filters): array
    {
        [$from, $to, $year] = $this->parseDateRange($filters);
        $rows = $this->buildRows($user, $filters, $from, $to, $year);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'rows' => $items,
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
            'charts' => $this->chartPayload($rows),
            'filters' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'year' => $year,
            ],
        ];
    }

    public function exportRows(User $user, array $filters): Collection
    {
        [$from, $to, $year] = $this->parseDateRange($filters);

        return $this->buildRows($user, $filters, $from, $to, $year);
    }

    public function detailTimeline(User $user, Employee $employee, LeaveType $leaveType, array $filters): array
    {
        if ((int) $employee->company_id !== (int) $user->company_id
            || (int) $leaveType->company_id !== (int) $user->company_id) {
            abort(404);
        }

        [$from, $to, $year] = $this->parseDateRange($filters);
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('year', $year)
            ->first();

        if (! $balance) {
            return [
                'employee' => $this->employeeSummary($employee),
                'policy' => ['id' => $leaveType->id, 'name' => $leaveType->name],
                'events' => [],
                'summary' => null,
            ];
        }

        $row = $this->buildRow($employee, $balance, $leaveType, $from, $to, $year);
        $events = $this->timelineEvents($employee->id, $leaveType->id, $from, $to, $year, $balance);

        return [
            'employee' => $this->employeeSummary($employee),
            'policy' => ['id' => $leaveType->id, 'name' => $leaveType->name],
            'summary' => $row,
            'events' => $events,
        ];
    }

    private function parseDateRange(array $filters): array
    {
        if (empty($filters['from_date']) || empty($filters['to_date'])) {
            throw ValidationException::withMessages([
                'from_date' => ['From date is required.'],
                'to_date' => ['To date is required.'],
            ]);
        }

        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => ['To date must be on or after from date.'],
            ]);
        }

        if ($from->year !== $to->year) {
            throw ValidationException::withMessages([
                'to_date' => ['Date range must fall within the same calendar year.'],
            ]);
        }

        return [$from, $to, $from->year];
    }

    private function buildRows(User $user, array $filters, Carbon $from, Carbon $to, int $year): Collection
    {
        $employees = $this->filteredEmployees($user->company_id, $filters);
        $leaveTypeQuery = LeaveType::query()->where('company_id', $user->company_id);

        if (($filters['policy_status'] ?? 'active') === 'active') {
            $leaveTypeQuery->where('status', 'active');
        } elseif (($filters['policy_status'] ?? '') === 'inactive') {
            $leaveTypeQuery->where('status', 'inactive');
        }

        if (! empty($filters['leave_type_id'])) {
            $leaveTypeQuery->where('id', (int) $filters['leave_type_id']);
        }

        $leaveTypes = $leaveTypeQuery->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');

        if ($leaveTypes->isEmpty() || $employees->isEmpty()) {
            return collect();
        }

        $employeeIds = $employees->pluck('id');
        $balances = EmployeeLeaveBalance::query()
            ->with(['leaveType', 'employee.department', 'employee.departments'])
            ->where('company_id', $user->company_id)
            ->where('year', $year)
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('leave_type_id', $leaveTypes->keys())
            ->get();

        $rows = collect();

        foreach ($balances as $balance) {
            $employee = $employees->get($balance->employee_id);
            $leaveType = $leaveTypes->get($balance->leave_type_id);

            if (! $employee || ! $leaveType) {
                continue;
            }

            $row = $this->buildRow($employee, $balance, $leaveType, $from, $to, $year);

            if (! $this->matchesSearch($row, $filters['search'] ?? null)) {
                continue;
            }

            if (($filters['assignment_status'] ?? 'active') === 'inactive') {
                continue;
            }

            $rows->push($row);
        }

        return $rows->sortBy([
            ['employee_name', 'asc'],
            ['policy_name', 'asc'],
        ])->values();
    }

    private function buildRow(
        Employee $employee,
        EmployeeLeaveBalance $balance,
        LeaveType $leaveType,
        Carbon $from,
        Carbon $to,
        int $year,
    ): array {
        $yearStart = Carbon::create($year, 1, 1)->toDateString();
        $yearEnd = Carbon::create($year, 12, 31)->toDateString();
        $isUnlimited = $leaveType->isUnlimitedLeave();

        $usedBefore = $this->sumApprovedLeaveDays(
            $employee->id,
            $leaveType->id,
            $yearStart,
            $from->copy()->subDay()->toDateString(),
        );
        $leavesTaken = $this->sumApprovedLeaveDays(
            $employee->id,
            $leaveType->id,
            $from->toDateString(),
            $to->toDateString(),
        );
        $usedAfter = $this->sumApprovedLeaveDays(
            $employee->id,
            $leaveType->id,
            $to->copy()->addDay()->toDateString(),
            $yearEnd,
        );

        $initialBalance = (float) $balance->allocated;
        $manualResetLeaves = (float) $balance->adjusted;
        $accruedLeaves = $this->accruedInPeriod($balance, $from, $to);
        $expirationChanges = 0.0;
        $carryForwardChanges = 0.0;

        $pendingReserve = $this->sumPendingLeaveDaysInPeriod($employee->id, $leaveType->id, $from, $to);

        if ($isUnlimited) {
            $fromBalance = null;
            $toBalance = null;
            $balanceChange = 0.0;
        } else {
            $fromBalance = max(0, round($initialBalance + $manualResetLeaves - $usedBefore, 2));
            $toBalance = max(0, round(
                $initialBalance + $manualResetLeaves - $usedBefore - $leavesTaken - $this->pendingDaysAfter($employee->id, $leaveType->id, $to, $balance),
                2,
            ));
            $balanceChange = round($toBalance - $fromBalance, 2);
        }

        $departmentName = $employee->departments->pluck('name')->filter()->implode(', ');
        if ($departmentName === '') {
            $departmentName = $employee->department?->name;
        }

        return [
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->full_name,
            'department' => $departmentName ?: '—',
            'designation' => $employee->designation ?: '—',
            'joining_date' => $employee->joining_date?->format('Y-m-d'),
            'joining_date_label' => $employee->joining_date?->format('d M Y') ?: '—',
            'employment_type' => $employee->employment_type,
            'employee_status' => $employee->status,
            'leave_type_id' => $leaveType->id,
            'policy_name' => $leaveType->name,
            'policy_status' => $leaveType->status,
            'balance_id' => $balance->id,
            'is_unlimited' => $isUnlimited,
            'unit' => $this->leaveBalanceService->balanceUnit($leaveType),
            'from_balance' => $fromBalance,
            'initial_balance' => $initialBalance,
            'accrued_leaves' => round($accruedLeaves, 2),
            'manual_reset_leaves' => round($manualResetLeaves, 2),
            'expiration_changes' => $expirationChanges,
            'carry_forward_changes' => $carryForwardChanges,
            'leaves_taken' => round($leavesTaken, 2),
            'pending_in_period' => round($pendingReserve, 2),
            'to_balance' => $toBalance,
            'balance_change' => $balanceChange,
            'balance_change_type' => $this->balanceChangeType($balanceChange, $isUnlimited),
        ];
    }

    private function timelineEvents(
        int $employeeId,
        int $leaveTypeId,
        Carbon $from,
        Carbon $to,
        int $year,
        EmployeeLeaveBalance $balance,
    ): array {
        $events = [];

        if ($balance->created_at && $balance->created_at->between($from, $to)) {
            $events[] = [
                'date' => $balance->created_at->toDateString(),
                'date_label' => $balance->created_at->format('d M Y'),
                'type' => 'allocation',
                'type_label' => 'Policy Assigned',
                'amount' => (float) $balance->allocated,
                'direction' => 'credit',
                'notes' => 'Annual allocation applied for '.$year,
            ];
        }

        if ((float) $balance->adjusted > 0) {
            $events[] = [
                'date' => $balance->updated_at?->toDateString() ?? $from->toDateString(),
                'date_label' => $balance->updated_at?->format('d M Y') ?? $from->format('d M Y'),
                'type' => 'manual_adjustment',
                'type_label' => 'Manual Adjustment / Comp Off',
                'amount' => (float) $balance->adjusted,
                'direction' => 'credit',
                'notes' => 'Current manual credit on balance record',
            ];
        }

        $leaveDays = LeaveRequestDay::query()
            ->select([
                'leave_request_days.date',
                'leave_request_days.day_value',
                'leave_request_days.session',
                'leave_requests.id as request_id',
                'leave_requests.status',
                'leave_requests.reason',
                'leave_requests.reviewed_at',
            ])
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_days.leave_request_id')
            ->where('leave_requests.employee_id', $employeeId)
            ->where('leave_requests.leave_type_id', $leaveTypeId)
            ->whereIn('leave_requests.status', [
                LeaveRequest::STATUS_APPROVED,
                LeaveRequest::STATUS_PENDING,
            ])
            ->whereDate('leave_request_days.date', '>=', $from->toDateString())
            ->whereDate('leave_request_days.date', '<=', $to->toDateString())
            ->orderBy('leave_request_days.date')
            ->get();

        foreach ($leaveDays as $day) {
            $events[] = [
                'date' => Carbon::parse($day->date)->toDateString(),
                'date_label' => Carbon::parse($day->date)->format('d M Y'),
                'type' => $day->status === LeaveRequest::STATUS_APPROVED ? 'leave_taken' : 'leave_pending',
                'type_label' => $day->status === LeaveRequest::STATUS_APPROVED ? 'Leave Taken' : 'Leave Pending',
                'amount' => (float) $day->day_value,
                'direction' => 'debit',
                'notes' => trim(($day->reason ?: 'Leave request #'.$day->request_id).' · '.$day->session),
            ];
        }

        return collect($events)
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function chartPayload(Collection $rows): array
    {
        $finiteRows = $rows->filter(fn (array $row) => ! $row['is_unlimited']);

        $byDepartment = $finiteRows
            ->groupBy('department')
            ->map(fn (Collection $group, string $department) => [
                'department' => $department,
                'balance_change' => round($group->sum('balance_change'), 2),
                'leaves_taken' => round($group->sum('leaves_taken'), 2),
                'employee_count' => $group->pluck('employee_id')->unique()->count(),
            ])
            ->sortByDesc('balance_change')
            ->values()
            ->all();

        $byChangeType = $finiteRows
            ->groupBy('balance_change_type')
            ->map(fn (Collection $group, string $type) => [
                'type' => $type,
                'type_label' => $this->changeTypeLabel($type),
                'count' => $group->count(),
            ])
            ->values()
            ->all();

        $byPolicy = $finiteRows
            ->groupBy('policy_name')
            ->map(fn (Collection $group, string $policy) => [
                'policy' => $policy,
                'balance_change' => round($group->sum('balance_change'), 2),
                'leaves_taken' => round($group->sum('leaves_taken'), 2),
            ])
            ->sortByDesc('leaves_taken')
            ->values()
            ->all();

        return [
            'balance_change_by_department' => $byDepartment,
            'balance_change_by_type' => $byChangeType,
            'leaves_taken_by_policy' => $byPolicy,
        ];
    }

    private function filteredEmployees(int $companyId, array $filters): Collection
    {
        $query = Employee::query()
            ->with(['department', 'departments'])
            ->where('company_id', $companyId);

        $status = $filters['employee_status'] ?? 'active';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $employmentType = $filters['employment_type'] ?? 'all';
        if ($employmentType !== 'all') {
            $query->where('employment_type', $employmentType);
        }

        if (! empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->where(function ($builder) use ($departmentId) {
                $builder->where('department_id', $departmentId)
                    ->orWhereHas('departments', fn ($dept) => $dept->where('departments.id', $departmentId));
            });
        }

        if (! empty($filters['employee_id'])) {
            $query->where('id', (int) $filters['employee_id']);
        }

        if (! empty($filters['designation'])) {
            $query->where('designation', $filters['designation']);
        }

        return $query->orderBy('first_name')->orderBy('last_name')->get()->keyBy('id');
    }

    private function sumApprovedLeaveDays(int $employeeId, int $leaveTypeId, string $from, string $to): float
    {
        if ($from > $to) {
            return 0.0;
        }

        return (float) LeaveRequestDay::query()
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_days.leave_request_id')
            ->where('leave_requests.employee_id', $employeeId)
            ->where('leave_requests.leave_type_id', $leaveTypeId)
            ->where('leave_requests.status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('leave_request_days.date', '>=', $from)
            ->whereDate('leave_request_days.date', '<=', $to)
            ->sum('leave_request_days.day_value');
    }

    private function sumPendingLeaveDaysInPeriod(int $employeeId, int $leaveTypeId, Carbon $from, Carbon $to): float
    {
        return (float) LeaveRequestDay::query()
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_days.leave_request_id')
            ->where('leave_requests.employee_id', $employeeId)
            ->where('leave_requests.leave_type_id', $leaveTypeId)
            ->where('leave_requests.status', LeaveRequest::STATUS_PENDING)
            ->whereDate('leave_request_days.date', '>=', $from->toDateString())
            ->whereDate('leave_request_days.date', '<=', $to->toDateString())
            ->sum('leave_request_days.day_value');
    }

    private function pendingDaysAfter(int $employeeId, int $leaveTypeId, Carbon $to, EmployeeLeaveBalance $balance): float
    {
        if ($to->isFuture() || $to->isToday()) {
            return (float) $balance->pending;
        }

        return $this->sumPendingLeaveDaysInPeriod(
            $employeeId,
            $leaveTypeId,
            $to->copy()->addDay(),
            Carbon::create($to->year, 12, 31),
        );
    }

    private function accruedInPeriod(EmployeeLeaveBalance $balance, Carbon $from, Carbon $to): float
    {
        if (! $balance->created_at || ! $balance->created_at->between($from, $to)) {
            return 0.0;
        }

        return (float) $balance->allocated;
    }

    private function balanceChangeType(float $change, bool $isUnlimited): string
    {
        if ($isUnlimited) {
            return 'unlimited';
        }

        if ($change > 0.001) {
            return 'increase';
        }

        if ($change < -0.001) {
            return 'decrease';
        }

        return 'no_change';
    }

    private function changeTypeLabel(string $type): string
    {
        return match ($type) {
            'increase' => 'Increase',
            'decrease' => 'Decrease',
            'no_change' => 'No Change',
            'unlimited' => 'Unlimited',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    private function matchesSearch(array $row, ?string $search): bool
    {
        if ($search === null || trim($search) === '') {
            return true;
        }

        $needle = strtolower(trim($search));

        return str_contains(strtolower($row['employee_name']), $needle)
            || str_contains(strtolower($row['policy_name']), $needle)
            || str_contains(strtolower((string) $row['employee_code']), $needle);
    }

    private function employeeSummary(Employee $employee): array
    {
        $departmentName = $employee->departments->pluck('name')->filter()->implode(', ');
        if ($departmentName === '') {
            $departmentName = $employee->department?->name;
        }

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'department' => $departmentName,
            'designation' => $employee->designation,
        ];
    }
}
