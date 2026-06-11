<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeComplianceField;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeePaymentMethod;
use App\Models\EmployeePersonalSection;
use App\Models\AttendanceRegularizationRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function __construct(
        private LeaveRequestService $leaveRequestService,
        private AttendanceRegularizationService $regularizationService,
        private EmployeeDocumentService $employeeDocumentService,
    ) {}

    public function forUser(User $user): array
    {
        $user->loadMissing(['company', 'role', 'employee']);

        if ($user->isSuperAdmin()) {
            return $this->forSuperAdmin($user);
        }

        if (! $user->company_id) {
            return $this->homeBase($user, [
                'role_label' => $user->role?->name ?? 'User',
                'widgets' => [],
            ]);
        }

        return match (true) {
            $user->isCompanyAdmin(), $user->isHrManager() => $this->forHrAdmin($user),
            $user->canApproveLeave() => $this->forApprover($user),
            default => $this->forEmployee($user),
        };
    }

    private function forSuperAdmin(User $user): array
    {
        $activeCompanies = Company::query()->where('status', 'active')->count();
        $inactiveCompanies = Company::query()->where('status', 'inactive')->count();

        return $this->homeBase($user, [
            'role_label' => 'Super Admin',
            'is_super_admin' => true,
            'widgets' => [
                $this->widget('companies_total', 'Total Companies', Company::count(), 'All registered tenants', 'primary', route('web.companies.index'), '🏢'),
                $this->widget('companies_active', 'Active Companies', $activeCompanies, 'Currently active', 'success', route('web.companies.index'), '✅'),
                $this->widget('companies_inactive', 'Inactive Companies', $inactiveCompanies, 'Suspended or inactive', 'warning', route('web.companies.index'), '⏸️'),
                $this->widget('employees_platform', 'Platform Employees', Employee::count(), 'Across all companies', 'info', route('web.companies.index'), '👥'),
            ],
            'show_punch_widget' => false,
            'policy_link' => route('web.companies.index'),
            'policy_label' => 'Manage companies',
            'new_joinees_title' => 'Latest Joinees (Platform)',
        ], platformScope: true);
    }

    private function forHrAdmin(User $user): array
    {
        $companyId = (int) $user->company_id;
        $attendance = $this->attendanceSnapshot($companyId);

        return $this->homeBase($user, [
            'role_label' => $user->isCompanyAdmin() ? 'Company Admin' : 'HR Manager',
            'is_super_admin' => false,
            'company' => $user->company ? ['name' => $user->company->name, 'status' => $user->company->status] : null,
            'widgets' => array_values(array_filter([
                $user->canViewEmployees() ? $this->widget('employees_active', 'Active Employees', $this->activeEmployeeCount($companyId), 'Workforce strength', 'primary', route('web.employees.index'), '👥') : null,
                $user->canViewAllAttendance() ? $this->widget('present_today', 'Present Today', $attendance['present'], 'Marked attendance today', 'success', route('web.attendance.index'), '✅') : null,
                $user->canViewAllAttendance() ? $this->widget('on_leave_today', 'On Leave Today', $attendance['on_leave'], 'Approved leave today', 'info', route('web.attendance.index'), '🏖️') : null,
                $user->canViewAllAttendance() ? $this->widget('absent_today', 'Absent Today', $attendance['absent'], 'No punch & not on leave', 'warning', route('web.attendance.index'), '⚠️') : null,
                $user->canApproveLeave() ? $this->widget('pending_leaves', 'Pending Leaves', $this->leaveRequestService->pendingCountForCompany($companyId, $user), 'Awaiting your approval', 'warning', route('web.leave.index').'?status=pending', '📅') : null,
                $user->canReviewEmployeeDocuments() ? $this->widget('pending_documents', 'Pending Documents', $this->pendingDocumentsCount($companyId), 'Employee uploads to review', 'danger', route('web.employees.index'), '📄') : null,
                $user->canReviewEmployeeDocuments() ? $this->widget('pending_profiles', 'Profile Reviews', $this->pendingProfileReviewsCount($companyId), 'Personal, family, compliance', 'danger', route('web.employees.index'), '📝') : null,
                $this->widget('proofs_due', 'Proofs Due', $this->pendingProofsDueCount($companyId, $user), 'Leave waiting for documents', 'warning', route('web.leave.index').'?status=pending', '📎'),
            ])),
            'show_punch_widget' => $user->canMarkAttendance(),
        ]);
    }

    private function forApprover(User $user): array
    {
        return $this->homeBase($user, [
            'role_label' => $user->role?->name ?? 'Approver',
            'is_super_admin' => false,
            'company' => $user->company ? ['name' => $user->company->name, 'status' => $user->company->status] : null,
            'widgets' => [],
            'show_punch_widget' => $user->canMarkAttendance(),
        ]);
    }

    private function forEmployee(User $user): array
    {
        return $this->homeBase($user, [
            'role_label' => $user->role?->name ?? 'Employee',
            'is_super_admin' => false,
            'company' => $user->company ? ['name' => $user->company->name, 'status' => $user->company->status] : null,
            'widgets' => [],
            'show_punch_widget' => $user->canMarkAttendance(),
        ]);
    }

    private function homeBase(User $user, array $overrides = [], bool $platformScope = false): array
    {
        $companyId = (int) ($user->company_id ?? 0);
        $scopeCompanyId = $platformScope ? null : ($companyId ?: null);

        return array_merge([
            'layout' => 'home',
            'greeting_name' => $this->greetingName($user),
            'role_label' => $user->role?->name ?? 'User',
            'celebrations' => $scopeCompanyId !== null || $platformScope
                ? $this->celebrations($scopeCompanyId, $platformScope)
                : $this->emptyCelebrations(),
            'pending_approvals' => $this->canSeePendingApprovals($user)
                ? $this->pendingApprovals($user)
                : [],
            'show_pending_approvals' => $this->canSeePendingApprovals($user),
            'new_joinees' => $scopeCompanyId !== null || $platformScope
                ? $this->newJoinees($scopeCompanyId, platformScope: $platformScope)
                : [],
            'new_joinees_title' => 'New Joinees',
            'quick_actions' => $this->quickActions($user),
            'widgets' => [],
            'show_punch_widget' => false,
            'policy_link' => route('web.leave.index'),
            'policy_label' => 'View leave policies',
            'timezone_label' => '(GMT+0530) IST – Asia/Kolkata',
            'refresh_seconds' => 30,
        ], $overrides);
    }

    private function canSeePendingApprovals(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        return $user->canApproveLeave()
            || $user->canApproveRegularization()
            || $user->canReviewEmployeeDocuments();
    }

    private function greetingName(User $user): string
    {
        $name = trim($user->employee?->first_name ?: strtok($user->name, ' ') ?: $user->name);

        return $name !== '' ? $name : 'there';
    }

    private function emptyCelebrations(): array
    {
        return [
            'birthdays_today' => [],
            'birthdays_upcoming' => [],
            'anniversaries_today' => [],
            'anniversaries_upcoming' => [],
        ];
    }

    private function celebrations(?int $companyId, bool $platformScope = false): array
    {
        $query = Employee::query()
            ->where('status', 'active');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $employees = $query->get(['id', 'company_id', 'first_name', 'last_name', 'employee_code', 'date_of_birth', 'joining_date']);

        $birthdaysToday = [];
        $birthdaysUpcoming = [];
        $anniversariesToday = [];
        $anniversariesUpcoming = [];
        $today = $this->today();
        $lookahead = $today->copy()->addYear();

        foreach ($employees as $employee) {
            if ($employee->date_of_birth) {
                $occasion = $this->nextOccurrence($employee->date_of_birth);

                if ($occasion->isSameDay($today)) {
                    $birthdaysToday[] = $this->employeeCard($employee, $occasion->format('d M'), $occasion->format('Y-m-d'), $platformScope);
                } elseif ($occasion->gt($today) && $occasion->lte($lookahead)) {
                    $birthdaysUpcoming[] = $this->employeeCard($employee, $occasion->format('d M'), $occasion->format('Y-m-d'), $platformScope);
                }
            }

            if ($employee->joining_date && $employee->joining_date->lt($today)) {
                $occasion = $this->nextOccurrence($employee->joining_date);
                $years = $this->workAnniversaryYears($employee, $occasion);

                if ($years < 1 || $occasion->gt($lookahead)) {
                    continue;
                }

                $card = array_merge(
                    $this->employeeCard($employee, $occasion->format('d M'), $occasion->format('Y-m-d'), $platformScope),
                    ['years' => $years]
                );

                if ($occasion->isSameDay($today)) {
                    $anniversariesToday[] = $card;
                } elseif ($occasion->gt($today)) {
                    $anniversariesUpcoming[] = $card;
                }
            }
        }

        $this->sortCardsAscending($birthdaysToday);
        $this->sortCardsAscending($birthdaysUpcoming);
        $this->sortCardsAscending($anniversariesToday);
        $this->sortCardsAscending($anniversariesUpcoming);

        return [
            'birthdays_today' => array_values($birthdaysToday),
            'birthdays_upcoming' => array_slice(array_values($birthdaysUpcoming), 0, 5),
            'anniversaries_today' => array_values($anniversariesToday),
            'anniversaries_upcoming' => array_slice(array_values($anniversariesUpcoming), 0, 5),
        ];
    }

    private function sortCardsAscending(array &$cards): void
    {
        usort($cards, function (array $a, array $b): int {
            $dateCompare = strcmp($a['occasion_date'] ?? '', $b['occasion_date'] ?? '');

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });
    }

    private function today(): Carbon
    {
        return Carbon::today(config('app.timezone'));
    }

    private function nextOccurrence(Carbon $date): Carbon
    {
        $today = $this->today();
        $occasion = Carbon::createFromDate(
            (int) $today->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d'),
            config('app.timezone')
        )->startOfDay();

        if ($occasion->lt($today)) {
            $occasion->addYear();
        }

        return $occasion;
    }

    private function workAnniversaryYears(Employee $employee, Carbon $occasion): int
    {
        $joiningDate = Carbon::parse(
            $employee->joining_date->toDateString(),
            config('app.timezone')
        )->startOfDay();

        return max(0, (int) $joiningDate->diffInYears($occasion));
    }

    private function employeeCard(Employee $employee, string $dateLabel, ?string $sortKey = null, bool $platformScope = false): array
    {
        $first = trim((string) $employee->first_name);
        $last = trim((string) $employee->last_name);
        $initials = strtoupper(substr($first, 0, 1).substr($last, 0, 1)) ?: '—';
        $occasionDate = $sortKey ?? '';

        return [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'initials' => $initials,
            'employee_code' => $employee->employee_code,
            'date_label' => $dateLabel,
            'occasion_date' => $occasionDate,
            'sort_key' => $occasionDate,
            'url' => $this->employeeCardUrl($employee, $platformScope),
        ];
    }

    private function employeeCardUrl(Employee $employee, bool $platformScope): string
    {
        if ($platformScope && $employee->company_id) {
            return route('web.companies.show', $employee->company_id);
        }

        return route('web.employees.show', $employee);
    }

    private function newJoinees(?int $companyId, int $limit = 3, bool $platformScope = false): array
    {
        $query = Employee::query()
            ->where('status', 'active')
            ->whereNotNull('joining_date');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->orderByDesc('joining_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Employee $employee) use ($platformScope) {
                $joinedLabel = $employee->joining_date?->format('d M Y') ?? '—';

                return array_merge(
                    $this->employeeCard($employee, $joinedLabel, $employee->joining_date?->format('Y-m-d') ?? '', $platformScope),
                    ['joined_label' => $joinedLabel]
                );
            })
            ->all();
    }

    private function pendingApprovals(User $user): array
    {
        $items = collect();

        $this->leaveRequestService->pendingForReviewer($user)->each(function (LeaveRequest $request) use ($items) {
            $items->push([
                'id' => 'leave-'.$request->id,
                'request_by' => $request->employee?->full_name ?? $request->appliedBy?->name ?? 'Employee',
                'employee_code' => $request->employee?->employee_code ?? '—',
                'request_type' => ($request->leaveType?->name ?? 'Leave').' Request',
                'requested_on' => $request->created_at?->toIso8601String(),
                'requested_on_label' => $request->created_at?->format('d M Y') ?? '—',
                'status' => 'Pending',
                'url' => route('web.leave.show', $request),
                'sort' => $request->created_at?->timestamp ?? 0,
            ]);
        });

        $this->regularizationService->pendingForReviewer($user)->each(function (AttendanceRegularizationRequest $request) use ($items) {
            $items->push([
                'id' => 'regularization-'.$request->id,
                'request_by' => $request->employee?->full_name ?? $request->appliedBy?->name ?? 'Employee',
                'employee_code' => $request->employee?->employee_code ?? '—',
                'request_type' => 'Attendance Regularization',
                'requested_on' => $request->created_at?->toIso8601String(),
                'requested_on_label' => $request->created_at?->format('d M Y') ?? '—',
                'status' => 'Pending',
                'url' => route('web.attendance.regularize.index'),
                'sort' => $request->created_at?->timestamp ?? 0,
            ]);
        });

        if ($user->canReviewEmployeeDocuments()) {
            $this->employeeDocumentService->pendingForReviewer($user)->each(function (EmployeeDocument $document) use ($items) {
                $items->push([
                    'id' => 'document-'.$document->id,
                    'request_by' => $document->employee?->full_name ?? 'Employee',
                    'employee_code' => $document->employee?->employee_code ?? '—',
                    'request_type' => ($document->documentType?->name ?? 'Document').' Upload',
                    'requested_on' => $document->created_at?->toIso8601String(),
                    'requested_on_label' => $document->created_at?->format('d M Y') ?? '—',
                    'status' => 'Pending',
                    'url' => route('web.employees.show', $document->employee_id),
                    'sort' => $document->created_at?->timestamp ?? 0,
                ]);
            });
        }

        return $items
            ->sortByDesc('sort')
            ->take(10)
            ->map(fn ($item) => collect($item)->except('sort')->all())
            ->values()
            ->all();
    }

    private function quickActions(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [
                [
                    'label' => 'Manage Companies',
                    'url' => route('web.companies.index'),
                    'enabled' => true,
                ],
                [
                    'label' => 'Add Company',
                    'url' => route('web.companies.create'),
                    'enabled' => true,
                ],
            ];
        }

        $actions = [];

        if ($user->canApplyLeave()) {
            $actions[] = [
                'label' => 'Apply Leave',
                'url' => route('web.leave.apply'),
                'enabled' => true,
            ];
        }

        if ($user->canRegularizeAttendance()) {
            $actions[] = [
                'label' => 'Regularize Attendance',
                'url' => route('web.attendance.regularize.index'),
                'enabled' => true,
            ];
        }

        if ($user->canViewPayroll()) {
            $actions[] = [
                'label' => 'My Payslips',
                'url' => route('web.payroll.my-payslips'),
                'enabled' => true,
            ];
        }

        return $actions;
    }

    private function widget(
        string $id,
        string $label,
        int|float|string $value,
        string $meta,
        string $variant,
        ?string $url = null,
        string $icon = '📊',
        bool $clickable = true,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'value' => $value,
            'meta' => $meta,
            'variant' => $variant,
            'url' => $clickable ? $url : null,
            'icon' => $icon,
            'clickable' => $clickable && $url !== null,
        ];
    }

    private function activeEmployeeCount(int $companyId): int
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();
    }

    private function attendanceSnapshot(int $companyId): array
    {
        $active = $this->activeEmployeeCount($companyId);
        $present = AttendancePunch::query()
            ->where('company_id', $companyId)
            ->where('punch_type', AttendancePunch::TYPE_IN)
            ->whereDate('punched_at', today())
            ->distinct('employee_id')
            ->count('employee_id');

        $onLeave = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->distinct('employee_id')
            ->count('employee_id');

        $absent = max(0, $active - $present - $onLeave);

        return [
            'present' => $present,
            'on_leave' => $onLeave,
            'absent' => $absent,
        ];
    }

    private function pendingDocumentsCount(int $companyId): int
    {
        return EmployeeDocument::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();
    }

    private function pendingProfileReviewsCount(int $companyId): int
    {
        return EmployeeComplianceField::query()->where('company_id', $companyId)->where('status', 'pending')->count()
            + EmployeePaymentMethod::query()->where('company_id', $companyId)->where('status', 'pending')->count()
            + EmployeeFamilyMember::query()->where('company_id', $companyId)->where('status', 'pending')->count()
            + EmployeePersonalSection::query()->where('company_id', $companyId)->where('status', 'pending')->count();
    }

    private function pendingProofsDueCount(int $companyId, User $user): int
    {
        return LeaveRequest::query()
            ->with(['leaveType', 'attachments', 'employee.user', 'appliedBy'])
            ->where('company_id', $companyId)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->get()
            ->filter(fn (LeaveRequest $request) => $user->canReviewLeaveRequest($request)
                && $request->leaveType?->requires_proof
                && $request->attachments->isEmpty())
            ->count();
    }

    private function myLeaveCount(User $user, string $status): int
    {
        if (! $user->employee) {
            return 0;
        }

        return LeaveRequest::query()
            ->where('employee_id', $user->employee->id)
            ->where('status', $status)
            ->count();
    }

    private function myProofsDueCount(User $user): int
    {
        if (! $user->employee) {
            return 0;
        }

        return LeaveRequest::query()
            ->with(['leaveType', 'attachments'])
            ->where('employee_id', $user->employee->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->get()
            ->filter(fn (LeaveRequest $request) => $request->leaveType?->requires_proof && $request->attachments->isEmpty())
            ->count();
    }

    private function availableLeaveDays(Employee $employee): float
    {
        return (float) EmployeeLeaveBalance::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', (int) now()->format('Y'))
            ->get()
            ->sum(function (EmployeeLeaveBalance $balance) {
                $available = $balance->available();

                return $available === PHP_FLOAT_MAX ? 0 : $available;
            });
    }

    private function todayAttendanceLabel(User $user): string
    {
        if (! $user->employee) {
            return '—';
        }

        $punchedIn = AttendancePunch::query()
            ->where('employee_id', $user->employee->id)
            ->where('punch_type', AttendancePunch::TYPE_IN)
            ->whereDate('punched_at', today())
            ->exists();

        if ($punchedIn) {
            return 'Present';
        }

        $onLeave = LeaveRequest::query()
            ->where('employee_id', $user->employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->exists();

        return $onLeave ? 'On Leave' : 'Not Marked';
    }

    private function todayAttendanceMeta(User $user): string
    {
        return match ($this->todayAttendanceLabel($user)) {
            'Present' => 'You punched in today',
            'On Leave' => 'Approved leave today',
            default => 'Tap to mark attendance',
        };
    }

    private function recentCompaniesActivity(): array
    {
        return Company::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Company $company) => [
                'title' => $company->name,
                'subtitle' => ucfirst($company->status).' company',
                'time' => $company->created_at?->diffForHumans(),
                'variant' => $company->status === 'active' ? 'success' : 'warning',
                'url' => route('web.companies.show', $company),
            ])
            ->all();
    }

    private function recentCompanyActivity(int $companyId, int $limit = 6): array
    {
        $items = collect();

        LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->where('company_id', $companyId)
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (LeaveRequest $request) use ($items) {
                $items->push([
                    'title' => ($request->employee?->full_name ?? 'Employee').' · '.$request->leaveType?->name,
                    'subtitle' => ucfirst($request->status).' leave request',
                    'time' => $request->created_at?->diffForHumans(),
                    'variant' => match ($request->status) {
                        LeaveRequest::STATUS_APPROVED => 'success',
                        LeaveRequest::STATUS_PENDING => 'warning',
                        LeaveRequest::STATUS_REJECTED => 'danger',
                        default => 'primary',
                    },
                    'url' => route('web.leave.show', $request),
                    'sort' => $request->created_at?->timestamp ?? 0,
                ]);
            });

        Employee::query()
            ->where('company_id', $companyId)
            ->latest()
            ->limit(3)
            ->get()
            ->each(function (Employee $employee) use ($items) {
                $items->push([
                    'title' => $employee->full_name,
                    'subtitle' => 'Employee profile',
                    'time' => $employee->created_at?->diffForHumans(),
                    'variant' => 'primary',
                    'url' => route('web.employees.show', $employee),
                    'sort' => $employee->created_at?->timestamp ?? 0,
                ]);
            });

        return $items->sortByDesc('sort')->take($limit)->values()->map(fn ($item) => collect($item)->except('sort')->all())->all();
    }

    private function recentEmployeeActivity(Employee $employee): array
    {
        return LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (LeaveRequest $request) => [
                'title' => $request->leaveType?->name ?? 'Leave',
                'subtitle' => ucfirst($request->status).' · '.$request->total_days.' day(s)',
                'time' => $request->created_at?->diffForHumans(),
                'variant' => match ($request->status) {
                    LeaveRequest::STATUS_APPROVED => 'success',
                    LeaveRequest::STATUS_PENDING => 'warning',
                    LeaveRequest::STATUS_REJECTED => 'danger',
                    default => 'primary',
                },
                'url' => route('web.leave.show', $request),
            ])
            ->all();
    }
}
