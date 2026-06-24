<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Goal;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\PerformanceKpi;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\Payslip;
use App\Models\PipPlan;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportsService
{
    public function __construct(
        private LeaveBalanceAnalyticsService $leaveBalanceAnalyticsService,
        private PayrollService $payrollService,
        private EmployeeAccessService $employeeAccessService,
        private RequestsSummaryService $requestsSummaryService,
    ) {}

    /** @return array<int, array{key: string, name: string, description: string, filters: array<int, string>}> */
    public function catalogForUser(User $user): array
    {
        return collect($this->definitions())
            ->filter(fn (array $def) => $this->canAccessReport($user, $def['key']))
            ->map(fn (array $def) => [
                'key' => $def['key'],
                'name' => $def['name'],
                'description' => $def['description'],
                'filters' => $def['filters'],
            ])
            ->values()
            ->all();
    }

    public function run(User $user, string $type, array $filters = []): array
    {
        $definition = $this->definition($type);

        if (! $this->canAccessReport($user, $type)) {
            throw new AccessDeniedHttpException('You do not have permission to view this report.');
        }

        $payload = match ($type) {
            'employees' => $this->employeesReport($user, $filters),
            'attendance' => $this->attendanceReport($user, $filters),
            'leave-requests' => $this->leaveRequestsReport($user, $filters),
            'leave-balances' => $this->leaveBalancesReport($user, $filters),
            'expenses' => $this->expensesReport($user, $filters),
            'payroll' => $this->payrollReport($user, $filters),
            'timesheets' => $this->timesheetsReport($user, $filters),
            'regularization' => $this->regularizationReport($user, $filters),
            'performance-reviews' => $this->performanceReviewsReport($user, $filters),
            'goals' => $this->goalsReport($user, $filters),
            'pips' => $this->pipsReport($user, $filters),
            'kpis' => $this->kpisReport($user, $filters),
            'requests-summary' => $this->requestsSummaryService->report($user, $filters),
            default => throw ValidationException::withMessages(['type' => ['Unknown report type.']]),
        };

        return array_merge($payload, [
            'report' => [
                'key' => $definition['key'],
                'name' => $definition['name'],
                'description' => $definition['description'],
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /** @return array{headings: string[], rows: array<int, array<int|string|null>>, report: array<string, string>} */
    public function export(User $user, string $type, array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['per_page'] = 100000;

        $payload = $this->run($user, $type, $filters);

        return [
            'headings' => $payload['headings'],
            'rows' => $payload['rows'],
            'report' => $payload['report'],
            'generated_at' => $payload['generated_at'],
        ];
    }

    public function canAccessReport(User $user, string $type): bool
    {
        return match ($type) {
            'employees' => $user->canViewEmployees(),
            'attendance' => $user->canViewAllAttendance(),
            'leave-requests' => $user->canViewAllLeaveRequests(),
            'leave-balances' => $user->canViewLeaveAnalytics(),
            'expenses' => $user->canViewAllExpenses(),
            'payroll' => $user->canManagePayroll(),
            'timesheets' => $user->canReviewTeamTimesheets() || $user->canManageProjects(),
            'regularization' => $user->canViewAllAttendance(),
            'performance-reviews' => $user->canManagePerformance(),
            'goals' => $user->canManagePerformance() || $user->canParticipateInPerformance(),
            'pips' => $user->canManagePips(),
            'kpis' => $user->canManagePerformance(),
            'requests-summary' => $user->canViewActivityLogs(),
            default => false,
        };
    }

    /** @return array{headings: string[], rows: array<int, array<int|string|null>>, pagination: array<string, int|null>} */
    private function paginateRows(array $headings, Collection $rows, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'headings' => $headings,
            'rows' => $items->all(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }

    private function employeesReport(User $user, array $filters): array
    {
        $query = Employee::query()
            ->with(['department', 'manager', 'shift'])
            ->where('company_id', $user->company_id)
            ->orderBy('employee_code');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        $headings = [
            'Employee Code', 'Full Name', 'Email', 'Phone', 'Department', 'Designation',
            'Manager', 'Employment Type', 'Status', 'Joining Date', 'Shift',
        ];

        $rows = $query->get()->map(fn (Employee $e) => [
            $e->employee_code,
            $e->full_name,
            $e->email,
            $e->phone,
            $e->department?->name,
            $e->designation,
            $e->manager?->full_name,
            ucfirst(str_replace('_', ' ', (string) $e->employment_type)),
            ucfirst((string) $e->status),
            $e->joining_date?->format('d-m-Y'),
            $e->shift?->name,
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function attendanceReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters);

        $employeeQuery = Employee::query()
            ->with('department')
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('first_name');

        if (! empty($filters['department_id'])) {
            $employeeQuery->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['employee_id'])) {
            $employeeQuery->where('id', $filters['employee_id']);
        }

        $employees = $employeeQuery->get();
        $employeeIds = $employees->pluck('id');

        $punches = AttendancePunch::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('punched_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (AttendancePunch $p) => $p->employee_id.'|'.$p->punched_at->toDateString());

        $headings = [
            'Employee Code', 'Full Name', 'Department', 'Date', 'First In', 'Last Out', 'Punch Count', 'Source Notes',
        ];

        $rows = collect();

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                $dateString = $date->toDateString();
                $dayPunches = $punches->get($employee->id.'|'.$dateString, collect());

                if ($dayPunches->isEmpty()) {
                    continue;
                }

                $firstIn = $dayPunches->firstWhere('punch_type', AttendancePunch::TYPE_IN);
                $lastOut = $dayPunches->where('punch_type', AttendancePunch::TYPE_OUT)->last();
                $hasRegularization = $dayPunches->contains(fn (AttendancePunch $p) => $p->isRegularized());

                $rows->push([
                    $employee->employee_code,
                    $employee->full_name,
                    $employee->department?->name,
                    $date->format('d-m-Y'),
                    $firstIn?->punched_at?->format('H:i'),
                    $lastOut?->punched_at?->format('H:i'),
                    $dayPunches->count(),
                    $hasRegularization ? 'Regularized' : 'Live',
                ]);
            }
        }

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function leaveRequestsReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, required: false);

        $query = LeaveRequest::query()
            ->with(['employee.department', 'leaveType', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('from_date');

        if ($from && $to) {
            $query->where(function ($builder) use ($from, $to) {
                $builder->whereBetween('from_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('to_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($overlap) use ($from, $to) {
                        $overlap->where('from_date', '<=', $from->toDateString())
                            ->where('to_date', '>=', $to->toDateString());
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        $headings = [
            'Employee Code', 'Full Name', 'Department', 'Leave Type', 'From', 'To',
            'Days', 'Status', 'Reason', 'Reviewed By', 'Reviewed At',
        ];

        $rows = $query->get()->map(fn (LeaveRequest $r) => [
            $r->employee?->employee_code,
            $r->employee?->full_name,
            $r->employee?->department?->name,
            $r->leaveType?->name,
            $r->from_date?->format('d-m-Y'),
            $r->to_date?->format('d-m-Y'),
            $r->total_days,
            ucfirst($r->status),
            $r->reason,
            $r->reviewedBy?->name,
            $r->reviewed_at?->format('d-m-Y H:i'),
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function leaveBalancesReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, required: true);

        $analyticsRows = $this->leaveBalanceAnalyticsService->exportRows($user, [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'employee_status' => $filters['employee_status'] ?? 'active',
            'employment_type' => $filters['employment_type'] ?? 'all',
            'policy_status' => $filters['policy_status'] ?? 'active',
            'assignment_status' => $filters['assignment_status'] ?? 'active',
            'department_id' => $filters['department_id'] ?? null,
            'leave_type_id' => $filters['leave_type_id'] ?? null,
        ]);

        $headings = [
            'Employee ID', 'Full Name', 'Department', 'Designation', 'Date of Joining',
            'Policy Name', 'From Balance', 'Initial Balance', 'Accrued Leaves', 'Manual Reset',
            'Expiration', 'Carry Forward', 'Leaves Taken', 'To Balance', 'Balance Change', 'Change Type',
        ];

        $rows = $analyticsRows->map(fn ($row) => [
            $row['employee_code'] ?? null,
            $row['employee_name'] ?? null,
            $row['department'] ?? null,
            $row['designation'] ?? null,
            $row['joining_date_label'] ?? null,
            $row['policy_name'] ?? null,
            $row['is_unlimited'] ?? false ? 'Unlimited' : ($row['from_balance'] ?? null),
            $row['initial_balance'] ?? null,
            $row['accrued_leaves'] ?? null,
            $row['manual_reset_leaves'] ?? null,
            $row['expiration_changes'] ?? null,
            $row['carry_forward_changes'] ?? null,
            $row['leaves_taken'] ?? null,
            $row['is_unlimited'] ?? false ? 'Unlimited' : ($row['to_balance'] ?? null),
            $row['balance_change'] ?? null,
            $row['balance_change_type'] ?? null,
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function expensesReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, required: false);

        $query = Expense::query()
            ->with(['employee', 'expenseType', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->where('is_independent', true)
            ->orderByDesc('expense_date');

        if ($from && $to) {
            $query->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Expense Date', 'Employee', 'Expense Type', 'Amount', 'Merchant', 'Reference',
            'Approval Status', 'Payout Status', 'Reviewed By', 'Description',
        ];

        $rows = $query->get()->map(fn (Expense $e) => [
            $e->expense_date?->format('d-m-Y'),
            $e->employee?->full_name,
            $e->expenseType?->name,
            number_format((float) $e->amount, 2, '.', ''),
            $e->merchant,
            $e->reference_number,
            ucfirst($e->status),
            ucfirst(str_replace('_', ' ', (string) $e->payout_status)),
            $e->reviewedBy?->name,
            $e->description,
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function payrollReport(User $user, array $filters): array
    {
        if (empty($filters['payroll_period_id'])) {
            throw ValidationException::withMessages([
                'payroll_period_id' => ['Payroll period is required for this report.'],
            ]);
        }

        $period = PayrollPeriod::query()
            ->where('company_id', $user->company_id)
            ->where('id', $filters['payroll_period_id'])
            ->firstOrFail();

        $payslips = $this->payrollService->listPayslipsForPeriod($period);

        $headings = [
            'Employee Code', 'Full Name', 'Designation', 'Department', 'Joining Date',
            'Payable Days', 'LOP Days', 'Paid Days', 'Gross Salary', 'Total Deductions', 'Net Salary',
        ];

        $rows = $payslips->map(fn (Payslip $p) => [
            $p->employee_code,
            $p->employee_name,
            $p->designation,
            $p->department_name,
            $p->joining_date?->format('d-m-Y'),
            (float) $p->payable_days,
            (float) $p->lop_days,
            round(max((float) $p->payable_days - (float) $p->lop_days, 0), 1),
            (float) $p->total_earnings,
            (float) $p->total_deductions,
            (float) $p->net_pay,
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function timesheetsReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters);

        $query = TimesheetEntry::query()
            ->with(['employee.department', 'project'])
            ->where('company_id', $user->company_id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('work_date');

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        $headings = [
            'Work Date', 'Employee Code', 'Full Name', 'Department', 'Project',
            'Start Time', 'End Time', 'Hours', 'Notes',
        ];

        $rows = $query->get()->map(fn (TimesheetEntry $entry) => [
            $entry->work_date?->format('d-m-Y'),
            $entry->employee?->employee_code,
            $entry->employee?->full_name,
            $entry->employee?->department?->name,
            $entry->project?->name,
            $entry->start_time,
            $entry->end_time,
            $entry->hours,
            $entry->notes,
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function regularizationReport(User $user, array $filters): array
    {
        [$from, $to] = $this->parseDateRange($filters, required: false);

        $query = AttendanceRegularizationRequest::query()
            ->with(['employee.department', 'appliedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('attendance_date');

        if ($from && $to) {
            $query->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Employee Code', 'Full Name', 'Department', 'Attendance Date', 'Requested Time',
            'Reason', 'Status', 'Applied By', 'Reviewed By', 'Reviewed At',
        ];

        $rows = $query->get()->map(fn (AttendanceRegularizationRequest $r) => [
            $r->employee?->employee_code,
            $r->employee?->full_name,
            $r->employee?->department?->name,
            $r->attendance_date?->format('d-m-Y'),
            $r->requested_punch_in?->format('H:i').' – '.$r->requested_punch_out?->format('H:i'),
            $r->reason,
            ucfirst($r->status),
            $r->appliedBy?->name,
            $r->reviewedBy?->name,
            $r->reviewed_at?->format('d-m-Y H:i'),
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function performanceReviewsReport(User $user, array $filters): array
    {
        $query = PerformanceReview::query()
            ->with(['cycle', 'reviewee', 'reviewer', 'reviewerUser', 'pair'])
            ->whereHas('cycle', fn ($q) => $q->where('company_id', $user->company_id))
            ->orderByDesc('updated_at');

        if (! empty($filters['cycle_id'])) {
            $query->where('cycle_id', $filters['cycle_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Cycle', 'Reviewee', 'Reviewer', 'Relationship', 'Status', 'Overall Rating', 'Submitted At',
        ];

        $rows = $query->get()->map(function (PerformanceReview $review) {
            $pair = $review->pair;

            return [
                $review->cycle?->name,
                $review->reviewee?->full_name,
                $review->reviewer?->full_name ?? $review->reviewerUser?->name,
                $pair?->relationship,
                ucfirst(str_replace('_', ' ', $review->status)),
                $review->overall_rating,
                $review->submitted_at?->format('d-m-Y H:i'),
            ];
        });

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function goalsReport(User $user, array $filters): array
    {
        $query = Goal::query()
            ->with(['employee.department', 'keyResults'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('updated_at');

        if (! $user->canManagePerformance() && $user->employee) {
            $query->where('employee_id', $user->employee->id);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Employee', 'Department', 'Goal Title', 'Period Start', 'Period End',
            'Progress %', 'Key Results', 'Status', 'Visibility',
        ];

        $rows = $query->get()->map(fn (Goal $goal) => [
            $goal->employee?->full_name,
            $goal->employee?->department?->name,
            $goal->title,
            $goal->period_start?->format('d-m-Y'),
            $goal->period_end?->format('d-m-Y'),
            $goal->progress,
            $goal->keyResults->count(),
            ucfirst($goal->status),
            ucfirst($goal->visibility),
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function pipsReport(User $user, array $filters): array
    {
        $query = PipPlan::query()
            ->with(['employee.department', 'manager', 'keyResults'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Employee', 'Department', 'Manager', 'Title', 'Start Date', 'End Date',
            'Milestones', 'Status', 'Outcome Notes',
        ];

        $rows = $query->get()->map(fn (PipPlan $pip) => [
            $pip->employee?->full_name,
            $pip->employee?->department?->name,
            $pip->manager?->full_name,
            $pip->title,
            $pip->start_date?->format('d-m-Y'),
            $pip->end_date?->format('d-m-Y'),
            $pip->keyResults->count(),
            ucfirst($pip->status),
            $pip->outcome_notes,
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    private function kpisReport(User $user, array $filters): array
    {
        $query = PerformanceKpi::query()
            ->with('employee.department')
            ->where('company_id', $user->company_id)
            ->orderByDesc('updated_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $headings = [
            'Employee', 'Department', 'KPI Title', 'Target', 'Current', 'Unit',
            'Progress %', 'Frequency', 'Period Start', 'Period End', 'Status',
        ];

        $rows = $query->get()->map(fn (PerformanceKpi $kpi) => [
            $kpi->employee?->full_name,
            $kpi->employee?->department?->name,
            $kpi->title,
            $kpi->target_value,
            $kpi->current_value,
            $kpi->unit,
            $kpi->progressPercent(),
            ucfirst($kpi->frequency),
            $kpi->period_start?->format('d-m-Y'),
            $kpi->period_end?->format('d-m-Y'),
            ucfirst($kpi->status),
        ]);

        return $this->paginateRows($headings, $rows, $filters);
    }

    public function payrollPeriodOptions(User $user): Collection
    {
        return $this->payrollService->listPeriods((int) $user->company_id);
    }

    public function reviewCycleOptions(User $user): Collection
    {
        return PerformanceReviewCycle::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('period_start')
            ->get(['id', 'name', 'status']);
    }

    /** @return array{from: Carbon, to: Carbon}|array{null, null} */
    private function parseDateRange(array $filters, bool $required = true): array
    {
        if (empty($filters['from_date']) || empty($filters['to_date'])) {
            if ($required) {
                throw ValidationException::withMessages([
                    'from_date' => ['From date is required.'],
                    'to_date' => ['To date is required.'],
                ]);
            }

            return [null, null];
        }

        $from = Carbon::parse($filters['from_date'])->startOfDay();
        $to = Carbon::parse($filters['to_date'])->endOfDay();

        if ($from->year !== $to->year) {
            throw ValidationException::withMessages([
                'to_date' => ['Date range must be within the same calendar year.'],
            ]);
        }

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => ['To date must be on or after from date.'],
            ]);
        }

        return [$from, $to];
    }

    private function definition(string $type): array
    {
        $definition = collect($this->definitions())->firstWhere('key', $type);

        if (! $definition) {
            throw ValidationException::withMessages(['type' => ['Unknown report type.']]);
        }

        return $definition;
    }

    /** @return array<int, array{key: string, name: string, description: string, filters: array<int, string>}> */
    private function definitions(): array
    {
        return [
            ['key' => 'employees', 'name' => 'Employee Master', 'description' => 'Complete employee directory with department, manager, and employment details.', 'filters' => ['status', 'department_id', 'employment_type']],
            ['key' => 'attendance', 'name' => 'Attendance Log', 'description' => 'Daily punch-in/out log for all employees in the selected date range.', 'filters' => ['from_date', 'to_date', 'department_id', 'employee_id']],
            ['key' => 'leave-requests', 'name' => 'Leave Requests', 'description' => 'All leave applications with approval status and reviewer details.', 'filters' => ['from_date', 'to_date', 'status', 'leave_type_id']],
            ['key' => 'leave-balances', 'name' => 'Leave Balances', 'description' => 'Leave balance movements by employee and policy for a date range.', 'filters' => ['from_date', 'to_date', 'department_id', 'leave_type_id']],
            ['key' => 'expenses', 'name' => 'Expense Claims', 'description' => 'Expense reimbursements with approval and payout status.', 'filters' => ['from_date', 'to_date', 'status']],
            ['key' => 'payroll', 'name' => 'Payroll Summary', 'description' => 'Payslip summary for a selected payroll period.', 'filters' => ['payroll_period_id']],
            ['key' => 'timesheets', 'name' => 'Timesheets', 'description' => 'Project timesheet entries by employee and date.', 'filters' => ['from_date', 'to_date', 'employee_id', 'project_id']],
            ['key' => 'regularization', 'name' => 'Attendance Regularization', 'description' => 'Regularization requests with approval workflow status.', 'filters' => ['from_date', 'to_date', 'status']],
            ['key' => 'performance-reviews', 'name' => 'Performance Reviews', 'description' => 'Review cycle completion status by reviewee and reviewer.', 'filters' => ['cycle_id', 'status']],
            ['key' => 'goals', 'name' => 'Goals & OKRs', 'description' => 'Employee goals with progress and key result counts.', 'filters' => ['status']],
            ['key' => 'pips', 'name' => 'Performance Improvement Plans', 'description' => 'Active and completed PIPs with milestone tracking.', 'filters' => ['status']],
            ['key' => 'kpis', 'name' => 'KPI Tracker', 'description' => 'Employee KPI targets, current values, and progress.', 'filters' => ['status']],
            ['key' => 'requests-summary', 'name' => 'Requests Summary', 'description' => 'Consolidated workflow audit for leave, attendance regularization, expenses, profile updates, and hiring requisitions.', 'filters' => ['from_date', 'to_date', 'request_types']],
        ];
    }
}
