<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BulkImport;
use App\Models\BulkImportRow;
use App\Models\Department;
use App\Models\DocumentLetter;
use App\Models\DocumentLetterTemplate;
use App\Models\Employee;
use App\Models\HelpdeskTicket;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

class EmployeeAssistantContextService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private LeaveBalanceService $leaveBalanceService,
        private AttendanceService $attendanceService,
        private AttendancePolicyService $attendancePolicyService,
    ) {}

    /** @return array<string, mixed> */
    public function buildForUser(User $user): array
    {
        $persona = $this->personaFor($user);
        $employee = $this->employeeAccessService->linkedEmployee($user);

        $context = [
            'persona' => $persona,
            'has_employee_profile' => (bool) $employee,
            'user_name' => $user->name,
            'capabilities' => $this->capabilitiesFor($user, $persona),
        ];

        if ($employee) {
            $employee->load(['department', 'departments', 'manager', 'shift', 'role', 'company']);
            $context['profile'] = $this->profileSummary($employee);

            if ($user->hasPermission('leave.apply')) {
                $context['leave_balances'] = $this->leaveBalancesSummary($employee);
                $context['leave_requests'] = $this->leaveRequestsSummary($employee);
            }

            if ($user->hasPermission('attendance.view')) {
                $context['attendance_today'] = $this->attendanceTodaySummary($user);
            }

            $context['upcoming_holidays'] = $this->upcomingHolidaysSummary((int) $employee->company_id);

            if ($user->hasPermission('payroll.view')) {
                $context['payslips'] = $this->payslipsSummary($employee);
                $context['latest_payslip_detail'] = $this->latestPayslipDetail($employee);
            }

            if ($user->canApplyHelpdesk()) {
                $context['my_helpdesk_tickets'] = $this->myHelpdeskTicketsSummary($employee);
            }
        }

        $context['company_policies'] = $this->policyDocumentsSummary((int) $user->company_id);

        if ($persona === 'hr' || $persona === 'admin') {
            $context = array_merge($context, $this->hrContext($user));
        }

        if ($persona === 'admin') {
            $context = array_merge($context, $this->adminContext($user));
        }

        return $context;
    }

    public function personaFor(User $user): string
    {
        if ($user->canManageCompanyMasters() || $user->hasPermission('settings.manage')) {
            return 'admin';
        }

        if ($user->canManageEmployees()
            || $user->canManageHelpdesk()
            || $user->canViewAllLeaveRequests()
            || $user->canViewAllAttendance()
            || $user->canManageHiring()
            || $user->canManagePerformance()) {
            return 'hr';
        }

        return 'employee';
    }

    /** @return array<int, string> */
    public function suggestedQuestionsFor(User $user): array
    {
        $persona = $this->personaFor($user);

        return match ($persona) {
            'admin' => [
                'Summarize company headcount and setup status',
                'What data quality issues exist?',
                'Summarize recent admin activity',
                'Which roles have the most users?',
                'What holidays are configured this year?',
            ],
            'hr' => [
                'Who is on leave today?',
                'How many leave requests are pending approval?',
                'Summarize today\'s attendance issues',
                'How many open helpdesk tickets are there?',
                'What employees have incomplete profiles?',
            ],
            default => [
                'What is my leave balance?',
                'What is my attendance status today?',
                'Who is my reporting manager?',
                'Do I have any pending leave requests?',
                'When is the next company holiday?',
            ],
        };
    }

    /** @return array<int, string> */
    private function capabilitiesFor(User $user, string $persona): array
    {
        $capabilities = ['personal HR self-service', 'company holidays', 'policy documents Q&A'];

        if ($user->hasPermission('leave.apply')) {
            $capabilities[] = 'leave balances and requests';
        }

        if ($user->hasPermission('attendance.view')) {
            $capabilities[] = 'attendance status';
        }

        if ($user->hasPermission('payroll.view')) {
            $capabilities[] = 'payslip explanation';
        }

        if ($user->canApplyHelpdesk()) {
            $capabilities[] = 'helpdesk ticket history';
        }

        if ($persona === 'hr') {
            $capabilities[] = 'team leave and attendance insights';
            $capabilities[] = 'pending approvals summary';
            $capabilities[] = 'helpdesk queue summary';
            $capabilities[] = 'data quality checks';
        }

        if ($persona === 'admin') {
            $capabilities[] = 'company analytics overview';
            $capabilities[] = 'audit log summary';
            $capabilities[] = 'roles and permissions overview';
            $capabilities[] = 'company setup completeness';
        }

        return $capabilities;
    }

    /** @return array<string, mixed> */
    private function hrContext(User $user): array
    {
        $companyId = (int) $user->company_id;
        $context = [];

        if ($user->canViewAllLeaveRequests() || $user->canApproveLeave()) {
            $context['pending_leave_requests'] = $this->pendingLeaveRequestsSummary($user, $companyId);
            $context['employees_on_leave_today'] = $this->employeesOnLeaveToday($companyId);
        }

        if ($user->canViewAllAttendance()) {
            $context['attendance_overview_today'] = $this->attendanceOverviewToday($user);
        }

        if ($user->canManageHelpdesk()) {
            $context['open_helpdesk_tickets'] = $this->openHelpdeskSummary($companyId);
        }

        if ($user->canManageEmployees()) {
            $context['data_quality'] = $this->dataQualitySummary($companyId);
        }

        if ($user->canManageHiring()) {
            $context['hiring_pipeline'] = $this->hiringPipelineSummary($companyId);
        }

        return $context;
    }

    /** @return array<string, mixed> */
    private function adminContext(User $user): array
    {
        $companyId = (int) $user->company_id;

        return [
            'company_setup' => $this->companySetupSummary($companyId),
            'roles_overview' => $this->rolesOverview($companyId),
            'recent_activity' => $this->recentActivitySummary($companyId),
            'analytics_snapshot' => $this->analyticsSnapshot($user),
        ];
    }

    /** @return array<string, mixed> */
    private function profileSummary(Employee $employee): array
    {
        $departments = $employee->departments->isNotEmpty()
            ? $employee->departments->pluck('name')->all()
            : array_filter([$employee->department?->name]);

        return [
            'full_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'email' => $employee->email,
            'designation' => $employee->designation,
            'employment_type' => $employee->employment_type,
            'is_paid_employee' => $employee->isPaidEmployee(),
            'departments' => $departments,
            'role' => $employee->role?->name,
            'manager_name' => $employee->manager?->full_name,
            'shift' => $employee->shift?->name,
            'shift_time_range' => $employee->shift?->time_range,
            'joining_date' => $employee->joining_date?->toDateString(),
            'status' => $employee->status,
            'probation_status' => $employee->probation_status,
            'company_name' => $employee->company?->name,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function leaveBalancesSummary(Employee $employee): array
    {
        $year = (int) now()->format('Y');

        return $this->leaveBalanceService
            ->ensureBalancesForEmployee($employee, $year)
            ->map(function ($balance) {
                $available = $balance->available();
                $unit = $balance->leaveType?->quotaUnit() ?? 'days';

                return [
                    'leave_type' => $balance->leaveType?->name,
                    'available' => $available === PHP_FLOAT_MAX ? 'Unlimited' : round($available, $balance->leaveType?->usesHourQuota() ? 2 : 1),
                    'allocated' => (float) $balance->allocated,
                    'used' => (float) $balance->used,
                    'pending' => (float) $balance->pending,
                    'unit' => $unit,
                    'year' => $balance->year,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function leaveRequestsSummary(Employee $employee): array
    {
        return LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (LeaveRequest $request) => [
                'leave_type' => $request->leaveType?->name,
                'status' => $request->status,
                'from_date' => $request->from_date?->toDateString(),
                'to_date' => $request->to_date?->toDateString(),
                'days' => (float) $request->total_days,
                'applied_on' => $request->created_at?->toDateString(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function attendanceTodaySummary(User $user): array
    {
        $status = $this->attendanceService->todayStatus($user);

        return [
            'status' => $status['status'] ?? null,
            'status_label' => $status['status_label'] ?? null,
            'punch_in' => $status['punch_in_label'] ?? null,
            'punch_out' => $status['punch_out_label'] ?? null,
            'worked_minutes' => $status['today_worked_minutes'] ?? 0,
            'required_minutes' => $status['required_minutes'] ?? 0,
            'awaiting_punch_out' => (bool) ($status['awaiting_punch_out'] ?? false),
            'can_mark' => (bool) ($status['can_mark'] ?? false),
            'day_message' => $status['day_message'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function upcomingHolidaysSummary(int $companyId): array
    {
        $start = now()->startOfDay();
        $end = now()->addYear()->endOfDay();
        $holidaysByDate = $this->attendancePolicyService->holidaysForRange($companyId, $start, $end);
        $seen = [];
        $results = [];

        foreach ($holidaysByDate->sortKeys() as $dateString => $holiday) {
            if (isset($seen[$holiday->id])) {
                continue;
            }

            $seen[$holiday->id] = true;
            $dateLabel = Carbon::parse($dateString)->format('l, d M Y');

            if ($holiday->isFixed()) {
                $dateLabel .= ' (every year)';
            } elseif (! $holiday->isSingleDay()) {
                $dateLabel = $holiday->displayDateLabel();
            }

            $results[] = [
                'name' => $holiday->name,
                'date' => $dateString,
                'date_label' => $dateLabel,
            ];

            if (count($results) >= 8) {
                break;
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function payslipsSummary(Employee $employee): array
    {
        $total = Payslip::query()->where('employee_id', $employee->id)->count();
        $latest = Payslip::query()
            ->with('payrollPeriod')
            ->where('employee_id', $employee->id)
            ->orderByDesc('payroll_period_id')
            ->first();

        return [
            'total_count' => $total,
            'latest_period_label' => $latest?->periodLabel(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestPayslipDetail(Employee $employee): ?array
    {
        $payslip = Payslip::query()
            ->with('payrollPeriod')
            ->where('employee_id', $employee->id)
            ->orderByDesc('payroll_period_id')
            ->first();

        if (! $payslip) {
            return null;
        }

        return [
            'period' => $payslip->periodLabel(),
            'payable_days' => (float) $payslip->payable_days,
            'lop_days' => (float) $payslip->lop_days,
            'earnings' => $payslip->earnings ?? [],
            'deductions' => $payslip->deductions ?? [],
            'total_earnings' => (float) $payslip->total_earnings,
            'total_deductions' => (float) $payslip->total_deductions,
            'net_pay' => (float) $payslip->net_pay,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function myHelpdeskTicketsSummary(Employee $employee): array
    {
        return HelpdeskTicket::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (HelpdeskTicket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'updated_at' => $ticket->updated_at?->toDateString(),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function policyDocumentsSummary(int $companyId): array
    {
        return DocumentLetter::query()
            ->where('company_id', $companyId)
            ->where('category', 'policy')
            ->whereIn('status', ['signed', 'pending_signature'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (DocumentLetter $letter) => [
                'title' => $letter->title,
                'status' => $letter->status,
                'issued_at' => $letter->issued_at?->toDateString(),
                'summary' => str(strip_tags((string) ($letter->rendered_html ?: $letter->body_html)))->limit(400)->value(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function pendingLeaveRequestsSummary(User $user, int $companyId): array
    {
        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->orderByDesc('created_at');

        if (! $user->canViewAllLeaveRequests()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);
            $teamIds = Employee::query()
                ->where('company_id', $companyId)
                ->where('manager_id', $employee?->id)
                ->pluck('id');

            $query->whereIn('employee_id', $teamIds);
        }

        $requests = $query->limit(10)->get();

        return [
            'count' => $query->count(),
            'items' => $requests->map(fn (LeaveRequest $request) => [
                'employee_name' => $request->employee?->full_name,
                'leave_type' => $request->leaveType?->name,
                'from_date' => $request->from_date?->toDateString(),
                'to_date' => $request->to_date?->toDateString(),
                'days' => (float) $request->total_days,
            ])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function employeesOnLeaveToday(int $companyId): array
    {
        $today = now()->toDateString();

        return LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->orderBy('from_date')
            ->limit(15)
            ->get()
            ->map(fn (LeaveRequest $request) => [
                'employee_name' => $request->employee?->full_name,
                'leave_type' => $request->leaveType?->name,
                'until' => $request->to_date?->toDateString(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function attendanceOverviewToday(User $user): array
    {
        $today = now()->toDateString();
        $overview = $this->attendanceService->todayOverview($user, $today);

        return [
            'date' => $today,
            'summary' => $overview['summary'] ?? [],
            'not_punched_in' => collect($overview['rows'] ?? [])
                ->filter(fn ($row) => in_array($row['status'] ?? '', ['absent', 'not_marked', 'late'], true))
                ->take(10)
                ->map(fn ($row) => [
                    'employee_name' => $row['employee_name'] ?? null,
                    'status' => $row['status_label'] ?? $row['status'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function openHelpdeskSummary(int $companyId): array
    {
        $query = HelpdeskTicket::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['resolved', 'closed']);

        return [
            'count' => $query->count(),
            'items' => $query->orderByDesc('updated_at')->limit(8)->get()->map(fn (HelpdeskTicket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function dataQualitySummary(int $companyId): array
    {
        $employees = Employee::query()->where('company_id', $companyId)->where('status', 'active')->get();
        $issues = [];

        foreach ($employees as $employee) {
            $missing = array_filter([
                ! $employee->department_id && $employee->departments()->count() === 0 ? 'department' : null,
                ! $employee->manager_id ? 'manager' : null,
                ! $employee->shift_id ? 'shift' : null,
                ! $employee->joining_date ? 'joining_date' : null,
                ! $employee->designation ? 'designation' : null,
                ! $employee->email ? 'email' : null,
            ]);

            if ($missing !== []) {
                $issues[] = [
                    'employee_name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'missing_fields' => array_values($missing),
                ];
            }
        }

        return [
            'active_employees' => $employees->count(),
            'incomplete_profiles' => count($issues),
            'issues' => array_slice($issues, 0, 15),
        ];
    }

    /** @return array<string, mixed> */
    private function hiringPipelineSummary(int $companyId): array
    {
        if (! class_exists(\App\Models\Candidate::class)) {
            return [];
        }

        return [
            'open_jobs' => \App\Models\JobPosting::query()->where('company_id', $companyId)->where('status', 'open')->count(),
            'active_candidates' => \App\Models\Candidate::query()->where('company_id', $companyId)->whereNotIn('stage', ['hired', 'rejected'])->count(),
            'pending_offers' => \App\Models\HiringOffer::query()->where('company_id', $companyId)->whereIn('status', ['draft', 'sent'])->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function companySetupSummary(int $companyId): array
    {
        return [
            'departments' => Department::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'shifts' => Shift::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'leave_types' => LeaveType::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'holidays' => Holiday::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'roles' => Role::query()->where('company_id', $companyId)->where('status', 'active')->count(),
            'active_employees' => Employee::query()->where('company_id', $companyId)->where('status', 'active')->count(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function rolesOverview(int $companyId): array
    {
        return Role::query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->orderByDesc('users_count')
            ->limit(10)
            ->get()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'slug' => $role->slug,
                'users_count' => $role->users_count,
                'is_system' => (bool) $role->is_system,
                'status' => $role->status,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function recentActivitySummary(int $companyId): array
    {
        return ActivityLog::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'module' => $log->module,
                'action' => $log->action,
                'summary' => $log->message ?: ($log->module.' '.$log->action),
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function analyticsSnapshot(User $user): array
    {
        $companyId = (int) $user->company_id;
        $today = now()->toDateString();
        $attendanceSummary = [];

        if ($user->canViewAllAttendance()) {
            $attendanceSummary = $this->attendanceService->todayOverview($user, $today)['summary'] ?? [];
        }

        return [
            'employees_on_leave_today' => count($this->employeesOnLeaveToday($companyId)),
            'pending_leave_requests' => LeaveRequest::query()->where('company_id', $companyId)->where('status', 'pending')->count(),
            'open_helpdesk_tickets' => HelpdeskTicket::query()->where('company_id', $companyId)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'attendance_date' => $today,
            'attendance_summary' => $attendanceSummary,
        ];
    }
}
