<?php

namespace App\Services;

use App\Models\AttendanceRegularizationRequest;
use App\Models\EmployeeComplianceField;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeePaymentMethod;
use App\Models\EmployeePersonalSection;
use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\JobRequisition;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RequestHubService
{
    public function __construct(
        private LeaveRequestService $leaveRequestService,
        private AttendanceRegularizationService $regularizationService,
        private EmployeeDocumentService $employeeDocumentService,
        private EmployeePaymentMethodService $employeePaymentMethodService,
        private EmployeeFamilyMemberService $employeeFamilyMemberService,
        private EmployeePersonalSectionService $employeePersonalSectionService,
        private EmployeeComplianceFieldService $employeeComplianceFieldService,
        private ExpenseService $expenseService,
        private ExpenseGroupService $expenseGroupService,
        private HiringService $hiringService,
    ) {}

    public function summaryForUser(User $user): array
    {
        $pending = $this->pendingForUser($user);

        return [
            'pending_count' => count($pending),
            'can_review' => $this->canReviewAny($user),
        ];
    }

    /** @return array{requests: array<int, array<string, mixed>>, pagination: array<string, int|null>} */
    public function pendingForUserPaginated(User $user, int $page = 1, int $perPage = 5): array
    {
        $all = $this->pendingForUser($user);
        $total = count($all);
        $perPage = max(1, min(50, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        return [
            'requests' => array_slice($all, $offset, $perPage),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /** @return array{processed: int, succeeded: int, failed: array<int, array{key: string, message: string}>} */
    public function bulkReview(User $user, string $action, array $items, ?string $notes = null): array
    {
        if (! $this->canReviewAny($user)) {
            throw new AccessDeniedHttpException('You are not allowed to review requests.');
        }

        if ($action === 'reject' && ! trim((string) $notes)) {
            throw ValidationException::withMessages([
                'notes' => ['Rejection reason is required.'],
            ]);
        }

        $succeeded = 0;
        $failed = [];

        foreach ($items as $item) {
            $kind = (string) ($item['kind'] ?? '');
            $target = (string) ($item['target'] ?? '');

            if ($kind === '' || $target === '') {
                $failed[] = ['key' => '', 'message' => 'Invalid request item.'];
                continue;
            }

            try {
                $this->reviewItem($user, $kind, $target, $action, $notes);
                $succeeded++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'key' => "{$kind}:{$target}",
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'processed' => count($items),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    public function canReviewAny(User $user): bool
    {
        return $user->canApproveLeave()
            || $user->canApproveRegularization()
            || $user->canReviewEmployeeDocuments()
            || $user->canApproveExpenses()
            || $user->canApproveRequisitions();
    }

    public function pendingForUser(User $user): array
    {
        $items = collect();

        if ($user->canApproveLeave()) {
            $this->leaveRequestService->pendingForReviewer($user)->each(function (LeaveRequest $request) use ($items) {
                $items->push($this->normalizeLeave($request, true));
            });
        }

        if ($user->canApproveRegularization()) {
            foreach ($this->regularizationService->pendingGroupsForReviewer($user) as $group) {
                $items->push($this->normalizeRegularizationGroup($group));
            }
        }

        if ($user->canReviewEmployeeDocuments()) {
            $this->employeeDocumentService->pendingForReviewer($user)->each(function (EmployeeDocument $document) use ($items, $user) {
                $items->push($this->normalizeDocument($document, $user->canReviewDocument($document)));
            });

            $this->employeePaymentMethodService->pendingForReviewer($user)->each(function (EmployeePaymentMethod $method) use ($items, $user) {
                $items->push($this->normalizePaymentMethod($method, $user->canReviewPaymentMethod($method)));
            });

            $this->employeeFamilyMemberService->pendingForReviewer($user)->each(function (EmployeeFamilyMember $member) use ($items, $user) {
                $items->push($this->normalizeFamilyMember($member, $user->canReviewFamilyMember($member)));
            });

            $this->employeePersonalSectionService->pendingForReviewer($user)->each(function (EmployeePersonalSection $section) use ($items, $user) {
                $items->push($this->normalizePersonalSection($section, $user->canReviewPersonalSection($section)));
            });

            $this->employeeComplianceFieldService->pendingForReviewer($user)->each(function (EmployeeComplianceField $field) use ($items, $user) {
                $items->push($this->normalizeComplianceField($field, $user->canReviewComplianceField($field)));
            });
        }

        if ($user->canApproveExpenses()) {
            $this->expenseService->pendingIndependentForReviewer($user)->each(function (Expense $expense) use ($items, $user) {
                $items->push($this->normalizeExpense($expense, true, $user));
            });

            $this->expenseGroupService->pendingForReviewer($user)->each(function (ExpenseGroup $group) use ($items, $user) {
                $items->push($this->normalizeExpenseGroup($group, true, $user));
            });
        }

        if ($user->canApproveRequisitions()) {
            $this->hiringService->pendingRequisitionsForReviewer($user)->each(function (JobRequisition $requisition) use ($items, $user) {
                $items->push($this->normalizeJobRequisition($requisition, true, $user));
            });
        }

        return $items
            ->sortByDesc('sort_at')
            ->values()
            ->all();
    }

    public function teamForUser(User $user, ?string $status = null): array
    {
        if (! $this->canReviewAny($user)) {
            return [];
        }

        $statuses = $status ? [$status] : ['approved', 'rejected', 'cancelled'];
        $items = collect();

        if ($user->canApproveLeave()) {
            LeaveRequest::query()
                ->with(['employee', 'leaveType', 'appliedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(100)
                ->get()
                ->filter(fn (LeaveRequest $request) => $this->canViewTeamLeave($user, $request))
                ->each(function (LeaveRequest $request) use ($user, $items) {
                    $items->push($this->normalizeLeave($request, false, $user));
                });
        }

        if ($user->canApproveRegularization()) {
            $requests = AttendanceRegularizationRequest::query()
                ->with(['employee', 'appliedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(200)
                ->get()
                ->filter(fn (AttendanceRegularizationRequest $request) => $this->canViewTeamRegularization($user, $request));

            foreach ($this->groupReviewedRegularizations($requests) as $group) {
                $items->push($this->normalizeRegularizationReviewedGroup($group));
            }
        }

        if ($user->canReviewEmployeeDocuments()) {
            EmployeeDocument::query()
                ->with(['employee', 'documentType', 'uploadedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeeDocument $document) => $this->canViewTeamDocument($user, $document))
                ->each(fn (EmployeeDocument $document) => $items->push($this->normalizeDocument($document, false)));

            EmployeePaymentMethod::query()
                ->with(['employee', 'submittedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeePaymentMethod $method) => $this->canViewTeamPaymentMethod($user, $method))
                ->each(fn (EmployeePaymentMethod $method) => $items->push($this->normalizePaymentMethod($method, false)));

            EmployeeFamilyMember::query()
                ->with(['employee', 'submittedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeeFamilyMember $member) => $this->canViewTeamFamilyMember($user, $member))
                ->each(fn (EmployeeFamilyMember $member) => $items->push($this->normalizeFamilyMember($member, false)));

            EmployeePersonalSection::query()
                ->with(['employee', 'submittedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeePersonalSection $section) => $this->canViewTeamPersonalSection($user, $section))
                ->each(fn (EmployeePersonalSection $section) => $items->push($this->normalizePersonalSection($section, false)));

            EmployeeComplianceField::query()
                ->with(['employee', 'submittedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeeComplianceField $field) => $this->canViewTeamComplianceField($user, $field))
                ->each(fn (EmployeeComplianceField $field) => $items->push($this->normalizeComplianceField($field, false)));
        }

        if ($user->canApproveExpenses()) {
            Expense::query()
                ->with(['employee', 'expenseType', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->where('is_independent', true)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (Expense $expense) => $this->canViewTeamExpense($user, $expense))
                ->each(fn (Expense $expense) => $items->push($this->normalizeExpense($expense, false, $user)));

            ExpenseGroup::query()
                ->with(['employee', 'expenses.expenseType', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (ExpenseGroup $group) => $this->canViewTeamExpenseGroup($user, $group))
                ->each(fn (ExpenseGroup $group) => $items->push($this->normalizeExpenseGroup($group, false, $user)));
        }

        return $items
            ->sortByDesc('sort_at')
            ->values()
            ->all();
    }

    public function mineForUser(User $user, ?string $status = null): array
    {
        $items = collect();
        $employeeId = $user->employee?->id;

        if ($user->canViewLeaveRequests()) {
            $leaveQuery = LeaveRequest::query()
                ->with(['employee', 'leaveType', 'appliedBy'])
                ->where('company_id', $user->company_id)
                ->latest();

            if ($user->canViewAllLeaveRequests()) {
                if ($employeeId) {
                    $leaveQuery->where('employee_id', $employeeId);
                }
            } elseif ($employeeId) {
                $leaveQuery->where('employee_id', $employeeId);
            } else {
                $leaveQuery->whereRaw('1 = 0');
            }

            if ($status) {
                $leaveQuery->where('status', $status);
            }

            $leaveQuery->limit(100)->get()->each(function (LeaveRequest $request) use ($user, $items) {
                $items->push($this->normalizeLeave($request, false, $user));
            });
        }

        if ($user->canViewRegularizationRequests()) {
            $regularizationQuery = AttendanceRegularizationRequest::query()
                ->with(['employee', 'appliedBy'])
                ->where('company_id', $user->company_id)
                ->latest();

            if ($user->canViewAllAttendance()) {
                if ($employeeId) {
                    $regularizationQuery->where('employee_id', $employeeId);
                }
            } elseif ($employeeId) {
                $regularizationQuery->where('employee_id', $employeeId);
            } else {
                $regularizationQuery->whereRaw('1 = 0');
            }

            if ($status) {
                $regularizationQuery->where('status', $status);
            }

            $regularizationQuery->limit(100)->get()->each(function (AttendanceRegularizationRequest $request) use ($user, $items) {
                $items->push($this->normalizeRegularization($request, false, $user));
            });
        }

        if ($employeeId) {
            $this->appendProfileSubmissions($items, $employeeId, $status);
        }

        if ($user->canViewExpenses() && $employeeId) {
            $expenseQuery = Expense::query()
                ->with(['employee', 'expenseType'])
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employeeId)
                ->where('is_independent', true)
                ->latest();

            if ($status) {
                $expenseQuery->where('status', $status);
            }

            $expenseQuery->limit(50)->get()->each(function (Expense $expense) use ($user, $items) {
                $items->push($this->normalizeExpense($expense, false, $user));
            });

            $groupQuery = ExpenseGroup::query()
                ->with(['employee', 'expenses.expenseType'])
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employeeId)
                ->latest();

            if ($status) {
                $groupQuery->where('status', $status);
            }

            $groupQuery->limit(50)->get()->each(function (ExpenseGroup $group) use ($user, $items) {
                $items->push($this->normalizeExpenseGroup($group, false, $user));
            });
        }

        if ($user->canCreateRequisition()) {
            $requisitionQuery = JobRequisition::query()
                ->with(['department', 'requestedBy'])
                ->where('company_id', $user->company_id)
                ->where('requested_by_user_id', $user->id)
                ->latest();

            if ($status) {
                $requisitionQuery->where('status', $status);
            }

            $requisitionQuery->limit(50)->get()->each(function (JobRequisition $requisition) use ($user, $items) {
                $items->push($this->normalizeJobRequisition($requisition, false, $user));
            });
        }

        return $items
            ->sortByDesc('sort_at')
            ->values()
            ->all();
    }

    private function appendProfileSubmissions(Collection $items, int $employeeId, ?string $status): void
    {
        $statuses = $status ? [$status] : ['pending', 'approved', 'rejected'];

        EmployeeDocument::query()
            ->with(['employee', 'documentType', 'uploadedBy'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeeDocument $document) => $items->push($this->normalizeDocument($document, false)));

        EmployeePaymentMethod::query()
            ->with(['employee', 'submittedBy'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeePaymentMethod $method) => $items->push($this->normalizePaymentMethod($method, false)));

        EmployeeFamilyMember::query()
            ->with(['employee', 'submittedBy'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeeFamilyMember $member) => $items->push($this->normalizeFamilyMember($member, false)));

        EmployeePersonalSection::query()
            ->with(['employee', 'submittedBy'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeePersonalSection $section) => $items->push($this->normalizePersonalSection($section, false)));

        EmployeeComplianceField::query()
            ->with(['employee', 'submittedBy'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeeComplianceField $field) => $items->push($this->normalizeComplianceField($field, false)));
    }

    private function canViewTeamLeave(User $user, LeaveRequest $request): bool
    {
        if (! $user->canViewLeaveRequest($request)) {
            return false;
        }

        if ($user->employee && (int) $user->employee->id === (int) $request->employee_id) {
            return false;
        }

        return true;
    }

    private function canViewTeamRegularization(User $user, AttendanceRegularizationRequest $request): bool
    {
        if ((int) $request->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->employee && (int) $user->employee->id === (int) $request->employee_id) {
            return false;
        }

        if ($user->isHrManager() && ! $user->isCompanyAdmin() && $user->employee && (int) $user->employee->id === (int) $request->employee_id) {
            return false;
        }

        if ($user->canViewAllAttendance()) {
            return true;
        }

        return $user->canApproveRegularization()
            && $user->isDirectReportingManagerOfEmployee($request->employee);
    }

    private function canViewTeamDocument(User $user, EmployeeDocument $document): bool
    {
        if ((int) $document->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->isHrManager() && ! $user->isCompanyAdmin() && $user->employee && (int) $user->employee->id === (int) $document->employee_id) {
            return false;
        }

        if ($document->uploadedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        return $user->canReviewEmployeeDocuments();
    }

    private function canViewTeamPaymentMethod(User $user, EmployeePaymentMethod $method): bool
    {
        if ((int) $method->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->isHrManager() && ! $user->isCompanyAdmin() && $user->employee && (int) $user->employee->id === (int) $method->employee_id) {
            return false;
        }

        if ($method->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        return $user->canReviewEmployeeDocuments();
    }

    private function canViewTeamFamilyMember(User $user, EmployeeFamilyMember $member): bool
    {
        if ((int) $member->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->isHrManager() && ! $user->isCompanyAdmin() && $user->employee && (int) $user->employee->id === (int) $member->employee_id) {
            return false;
        }

        if ($member->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        return $user->canReviewEmployeeDocuments();
    }

    private function canViewTeamPersonalSection(User $user, EmployeePersonalSection $section): bool
    {
        if ((int) $section->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->isHrManager() && ! $user->isCompanyAdmin() && $user->employee && (int) $user->employee->id === (int) $section->employee_id) {
            return false;
        }

        if ($section->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        return $user->canReviewEmployeeDocuments();
    }

    private function canViewTeamComplianceField(User $user, EmployeeComplianceField $field): bool
    {
        if ((int) $field->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($user->isHrManager() && ! $user->isCompanyAdmin() && $user->employee && (int) $user->employee->id === (int) $field->employee_id) {
            return false;
        }

        if ($field->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        return $user->canReviewEmployeeDocuments();
    }

    /**
     * @param  Collection<int, AttendanceRegularizationRequest>  $requests
     * @return array<int, array<string, mixed>>
     */
    private function groupReviewedRegularizations(Collection $requests): array
    {
        $groups = [];

        foreach ($requests as $request) {
            $groupKey = $request->batch_id ?: ('single-'.$request->id);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'batch_id' => $request->batch_id,
                    'employee' => $request->employee ? [
                        'id' => $request->employee->id,
                        'full_name' => $request->employee->full_name,
                        'employee_code' => $request->employee->employee_code,
                    ] : null,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'reviewed_at_label' => $request->reviewed_at?->format('d M Y, h:i A'),
                    'reviewed_by_name' => $request->reviewedBy?->name,
                    'sort_at' => ($request->reviewed_at ?? $request->updated_at)?->timestamp ?? 0,
                    'dates' => [],
                    'request_ids' => [],
                ];
            }

            $groups[$groupKey]['dates'][] = [
                'id' => $request->id,
                'attendance_date' => $request->attendance_date?->toDateString(),
                'attendance_date_label' => $request->attendance_date?->format('D, d M Y'),
                'attendance_date_short_label' => $request->attendance_date?->format('D, d M'),
            ];
            $groups[$groupKey]['request_ids'][] = $request->id;
        }

        $grouped = array_values($groups);

        foreach ($grouped as &$group) {
            usort($group['dates'], fn (array $left, array $right) => strcmp($left['attendance_date'], $right['attendance_date']));
            $group['day_count'] = count($group['dates']);
        }
        unset($group);

        usort($grouped, fn (array $left, array $right) => ($right['sort_at'] ?? 0) <=> ($left['sort_at'] ?? 0));

        return $grouped;
    }

    private function reviewMeta(object $model): array
    {
        $reviewedAt = $model->reviewed_at ?? null;

        return [
            'reviewed_at_label' => $reviewedAt?->format('d M Y, h:i A'),
            'reviewed_by_name' => $model->reviewedBy?->name ?? null,
        ];
    }

    private function normalizeLeave(LeaveRequest $request, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();
        $dateSummary = $request->from_date?->equalTo($request->to_date)
            ? $request->from_date->format('d M Y')
            : ($request->from_date?->format('d M Y').' - '.$request->to_date?->format('d M Y'));

        return [
            'key' => 'leave:'.$request->id,
            'category' => 'leave',
            'category_label' => 'Leave',
            'entity_id' => $request->id,
            'batch_id' => null,
            'requester_name' => $request->employee?->full_name ?? 'Employee',
            'requester_code' => $request->employee?->employee_code,
            'subject' => $request->leaveType?->name ?? 'Leave Request',
            'detail' => trim(($dateSummary ?: '').($request->total_days ? ' · '.$request->total_days.' day(s)' : '')),
            'reason' => $request->reason,
            'status' => $request->status,
            'status_label' => ucfirst($request->status),
            'submitted_at_label' => $request->created_at?->format('d M Y, h:i A'),
            'sort_at' => ($request->reviewed_at ?? $request->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewLeaveRequest($request) ?? false),
            'can_cancel' => ! $forApproval && ($viewer?->canCancelLeaveRequest($request) ?? false),
            'view_url' => $this->requestShowUrl('leave', (string) $request->id),
            'review_kind' => 'leave',
            'review_target' => (string) $request->id,
            ...$this->reviewMeta($request),
        ];
    }

    private function normalizeRegularizationGroup(array $group): array
    {
        $dayCount = $group['day_count'] ?? count($group['dates'] ?? []);
        $dateLabels = collect($group['dates'] ?? [])
            ->pluck('attendance_date_short_label')
            ->filter()
            ->implode(', ');

        return [
            'key' => 'regularization-batch:'.($group['batch_id'] ?? implode('-', $group['request_ids'] ?? [])),
            'category' => 'regularization',
            'category_label' => 'Regularization',
            'entity_id' => $group['request_ids'][0] ?? null,
            'batch_id' => $group['batch_id'] ?? null,
            'requester_name' => $group['employee']['full_name'] ?? 'Employee',
            'requester_code' => $group['employee']['employee_code'] ?? null,
            'subject' => $dayCount > 1 ? "{$dayCount} day(s)" : ($group['dates'][0]['attendance_date_label'] ?? 'Attendance'),
            'detail' => collect([
                $dateLabels ?: null,
                ($group['original_punch_in_label'] ?? null) || ($group['original_punch_out_label'] ?? null)
                    ? 'Current '.collect([
                        ! empty($group['original_punch_in_label']) ? 'In '.$group['original_punch_in_label'] : null,
                        ! empty($group['original_punch_out_label']) ? 'Out '.$group['original_punch_out_label'] : null,
                    ])->filter()->implode(' · ')
                    : null,
                ($group['requested_punch_in_label'] ?? null) || ($group['requested_punch_out_label'] ?? null)
                    ? 'New '.collect([
                        ! empty($group['requested_punch_in_label']) ? 'In '.$group['requested_punch_in_label'] : null,
                        ! empty($group['requested_punch_out_label']) ? 'Out '.$group['requested_punch_out_label'] : null,
                    ])->filter()->implode(' · ')
                    : null,
            ])->filter()->implode(' · '),
            'reason' => $group['reason'] ?? null,
            'status' => 'pending',
            'status_label' => 'Pending',
            'submitted_at_label' => $group['created_at_label'] ?? null,
            'sort_at' => $group['sort_at'] ?? 0,
            'can_review' => (bool) ($group['can_review'] ?? false),
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl(
                ($dayCount > 1 && ! empty($group['batch_id'])) ? 'regularization-batch' : 'regularization',
                ($dayCount > 1 && ! empty($group['batch_id']))
                    ? (string) $group['batch_id']
                    : (string) ($group['request_ids'][0] ?? ''),
            ),
            'review_kind' => ($dayCount > 1 && ! empty($group['batch_id'])) ? 'regularization_batch' : 'regularization',
            'review_target' => ($dayCount > 1 && ! empty($group['batch_id']))
                ? $group['batch_id']
                : (string) ($group['request_ids'][0] ?? ''),
        ];
    }

    private function normalizeRegularizationReviewedGroup(array $group): array
    {
        $dayCount = $group['day_count'] ?? count($group['dates'] ?? []);
        $dateLabels = collect($group['dates'] ?? [])
            ->pluck('attendance_date_short_label')
            ->filter()
            ->implode(', ');

        return [
            'key' => 'regularization-reviewed:'.($group['batch_id'] ?? implode('-', $group['request_ids'] ?? [])),
            'category' => 'regularization',
            'category_label' => 'Regularization',
            'entity_id' => $group['request_ids'][0] ?? null,
            'batch_id' => $group['batch_id'] ?? null,
            'requester_name' => $group['employee']['full_name'] ?? 'Employee',
            'requester_code' => $group['employee']['employee_code'] ?? null,
            'subject' => $dayCount > 1 ? "{$dayCount} day(s)" : ($group['dates'][0]['attendance_date_label'] ?? 'Attendance'),
            'detail' => $dateLabels ?: null,
            'reason' => $group['reason'] ?? null,
            'status' => $group['status'] ?? 'approved',
            'status_label' => ucfirst($group['status'] ?? 'approved'),
            'submitted_at_label' => null,
            'sort_at' => $group['sort_at'] ?? 0,
            'can_review' => false,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl(
                ! empty($group['batch_id']) ? 'regularization-batch' : 'regularization',
                ! empty($group['batch_id'])
                    ? (string) $group['batch_id']
                    : (string) ($group['request_ids'][0] ?? ''),
            ),
            'review_kind' => null,
            'review_target' => null,
            'reviewed_at_label' => $group['reviewed_at_label'] ?? null,
            'reviewed_by_name' => $group['reviewed_by_name'] ?? null,
        ];
    }

    private function normalizeRegularization(AttendanceRegularizationRequest $request, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();

        return [
            'key' => 'regularization:'.$request->id,
            'category' => 'regularization',
            'category_label' => 'Regularization',
            'entity_id' => $request->id,
            'batch_id' => $request->batch_id,
            'requester_name' => $request->employee?->full_name ?? 'Employee',
            'requester_code' => $request->employee?->employee_code,
            'subject' => $request->attendance_date?->format('D, d M Y') ?? 'Attendance',
            'detail' => collect([
                $request->attendance_date?->format('D, d M Y'),
                ($request->original_punch_in?->format('h:i A') || $request->original_punch_out?->format('h:i A'))
                    ? 'Current '.collect([
                        $request->original_punch_in?->format('h:i A') ? 'In '.$request->original_punch_in->format('h:i A') : null,
                        $request->original_punch_out?->format('h:i A') ? 'Out '.$request->original_punch_out->format('h:i A') : null,
                    ])->filter()->implode(' · ')
                    : null,
                ($request->requested_punch_in?->format('h:i A') || $request->requested_punch_out?->format('h:i A'))
                    ? 'New '.collect([
                        $request->requested_punch_in?->format('h:i A') ? 'In '.$request->requested_punch_in->format('h:i A') : null,
                        $request->requested_punch_out?->format('h:i A') ? 'Out '.$request->requested_punch_out->format('h:i A') : null,
                    ])->filter()->implode(' · ')
                    : null,
            ])->filter()->implode(' · '),
            'reason' => $request->reason,
            'status' => $request->status,
            'status_label' => ucfirst($request->status),
            'submitted_at_label' => $request->created_at?->format('d M Y, h:i A'),
            'sort_at' => ($request->reviewed_at ?? $request->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewRegularizationRequest($request) ?? false),
            'can_cancel' => ! $forApproval && ($viewer?->canCancelRegularizationRequest($request) ?? false),
            'view_url' => $this->requestShowUrl('regularization', (string) $request->id),
            'review_kind' => 'regularization',
            'review_target' => (string) $request->id,
            ...$this->reviewMeta($request),
        ];
    }

    private function normalizeDocument(EmployeeDocument $document, bool $canReview): array
    {
        return [
            'key' => 'document:'.$document->id,
            'category' => 'document',
            'category_label' => 'Document',
            'entity_id' => $document->id,
            'batch_id' => null,
            'requester_name' => $document->employee?->full_name ?? 'Employee',
            'requester_code' => $document->employee?->employee_code,
            'subject' => $document->documentType?->name ?? 'Document Upload',
            'detail' => $document->original_name,
            'reason' => $document->notes,
            'status' => $document->status,
            'status_label' => ucfirst($document->status),
            'submitted_at_label' => $document->created_at?->format('d M Y, h:i A'),
            'sort_at' => ($document->reviewed_at ?? $document->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('document', (string) $document->id),
            'review_kind' => 'document',
            'review_target' => (string) $document->id,
            ...$this->reviewMeta($document),
        ];
    }

    private function normalizePaymentMethod(EmployeePaymentMethod $method, bool $canReview): array
    {
        return [
            'key' => 'payment_method:'.$method->id,
            'category' => 'payment_method',
            'category_label' => 'Payment',
            'entity_id' => $method->id,
            'batch_id' => null,
            'requester_name' => $method->employee?->full_name ?? 'Employee',
            'requester_code' => $method->employee?->employee_code,
            'subject' => ucfirst(str_replace('_', ' ', $method->payment_mode ?? 'Payment Method')),
            'detail' => $method->bank_name ?: $method->account_holder_name,
            'reason' => $method->notes,
            'status' => $method->status,
            'status_label' => ucfirst($method->status),
            'submitted_at_label' => ($method->submitted_at ?? $method->created_at)?->format('d M Y, h:i A'),
            'sort_at' => ($method->reviewed_at ?? $method->submitted_at ?? $method->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('payment_method', (string) $method->id),
            'review_kind' => 'payment_method',
            'review_target' => (string) $method->id,
            ...$this->reviewMeta($method),
        ];
    }

    private function normalizeFamilyMember(EmployeeFamilyMember $member, bool $canReview): array
    {
        return [
            'key' => 'family_member:'.$member->id,
            'category' => 'family_member',
            'category_label' => 'Family',
            'entity_id' => $member->id,
            'batch_id' => null,
            'requester_name' => $member->employee?->full_name ?? 'Employee',
            'requester_code' => $member->employee?->employee_code,
            'subject' => $member->name ?? 'Family Member',
            'detail' => $member->relation,
            'reason' => $member->notes,
            'status' => $member->status,
            'status_label' => ucfirst($member->status),
            'submitted_at_label' => ($member->submitted_at ?? $member->created_at)?->format('d M Y, h:i A'),
            'sort_at' => ($member->reviewed_at ?? $member->submitted_at ?? $member->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('family_member', (string) $member->id),
            'review_kind' => 'family_member',
            'review_target' => (string) $member->id,
            ...$this->reviewMeta($member),
        ];
    }

    private function normalizePersonalSection(EmployeePersonalSection $section, bool $canReview): array
    {
        return [
            'key' => 'personal_section:'.$section->id,
            'category' => 'personal_section',
            'category_label' => 'Personal',
            'entity_id' => $section->id,
            'batch_id' => null,
            'requester_name' => $section->employee?->full_name ?? 'Employee',
            'requester_code' => $section->employee?->employee_code,
            'subject' => EmployeePersonalSection::SECTION_LABELS[$section->section_type] ?? ucfirst(str_replace('_', ' ', $section->section_type ?? 'Personal Section')),
            'detail' => 'Profile update',
            'reason' => $section->notes,
            'status' => $section->status,
            'status_label' => ucfirst($section->status),
            'submitted_at_label' => ($section->submitted_at ?? $section->created_at)?->format('d M Y, h:i A'),
            'sort_at' => ($section->reviewed_at ?? $section->submitted_at ?? $section->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('personal_section', (string) $section->id),
            'review_kind' => 'personal_section',
            'review_target' => (string) $section->id,
            ...$this->reviewMeta($section),
        ];
    }

    private function normalizeComplianceField(EmployeeComplianceField $field, bool $canReview): array
    {
        return [
            'key' => 'compliance_field:'.$field->id,
            'category' => 'compliance_field',
            'category_label' => 'Compliance',
            'entity_id' => $field->id,
            'batch_id' => null,
            'requester_name' => $field->employee?->full_name ?? 'Employee',
            'requester_code' => $field->employee?->employee_code,
            'subject' => strtoupper($field->field_type ?? 'Compliance'),
            'detail' => $field->value,
            'reason' => $field->notes,
            'status' => $field->status,
            'status_label' => ucfirst($field->status),
            'submitted_at_label' => ($field->submitted_at ?? $field->created_at)?->format('d M Y, h:i A'),
            'sort_at' => ($field->reviewed_at ?? $field->submitted_at ?? $field->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('compliance_field', (string) $field->id),
            'review_kind' => 'compliance_field',
            'review_target' => (string) $field->id,
            ...$this->reviewMeta($field),
        ];
    }

    private function normalizeExpense(Expense $expense, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();

        return [
            'key' => 'expense:'.$expense->id,
            'category' => 'expense',
            'category_label' => 'Expense',
            'entity_id' => $expense->id,
            'batch_id' => null,
            'requester_name' => $expense->employee?->full_name ?? 'Employee',
            'requester_code' => $expense->employee?->employee_code,
            'subject' => $expense->expenseType?->name ?? 'Expense Claim',
            'detail' => ($expense->expense_date?->format('d M Y') ?? '').' · ₹'.number_format((float) $expense->amount, 2),
            'reason' => $expense->description,
            'status' => $expense->status,
            'status_label' => ucfirst($expense->status),
            'submitted_at_label' => $expense->created_at?->format('d M Y, h:i A'),
            'sort_at' => ($expense->reviewed_at ?? $expense->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewExpense($expense) ?? false),
            'can_cancel' => ! $forApproval && $expense->is_independent && in_array($expense->status, ['draft', 'pending'], true)
                && $viewer?->employee && (int) $viewer->employee->id === (int) $expense->employee_id,
            'view_url' => $this->requestShowUrl('expense', (string) $expense->id),
            'review_kind' => 'expense',
            'review_target' => (string) $expense->id,
            ...$this->reviewMeta($expense),
        ];
    }

    private function normalizeExpenseGroup(ExpenseGroup $group, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();
        $group->loadMissing('expenses');

        return [
            'key' => 'expense_group:'.$group->id,
            'category' => 'expense_group',
            'category_label' => 'Expense Group',
            'entity_id' => $group->id,
            'batch_id' => null,
            'requester_name' => $group->employee?->full_name ?? 'Employee',
            'requester_code' => $group->employee?->employee_code,
            'subject' => $group->name,
            'detail' => ($group->from_date?->format('d M Y') ?? '').' - '.($group->to_date?->format('d M Y') ?? '')
                .' · ₹'.number_format($group->totalAmount(), 2),
            'reason' => $group->description,
            'status' => $group->status,
            'status_label' => ucfirst($group->status),
            'submitted_at_label' => $group->created_at?->format('d M Y, h:i A'),
            'sort_at' => ($group->reviewed_at ?? $group->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewExpenseGroup($group) ?? false),
            'can_cancel' => ! $forApproval && in_array($group->status, ['draft', 'pending'], true)
                && $viewer?->employee && (int) $viewer->employee->id === (int) $group->employee_id,
            'view_url' => $this->requestShowUrl('expense_group', (string) $group->id),
            'review_kind' => 'expense_group',
            'review_target' => (string) $group->id,
            ...$this->reviewMeta($group),
        ];
    }

    private function normalizeJobRequisition(JobRequisition $requisition, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();
        $requisition->loadMissing(['department', 'requestedBy']);

        return [
            'key' => 'job_requisition:'.$requisition->id,
            'category' => 'job_requisition',
            'category_label' => 'Job Requisition',
            'entity_id' => $requisition->id,
            'batch_id' => null,
            'requester_name' => $requisition->requestedBy?->name ?? 'Requester',
            'requester_code' => null,
            'subject' => $requisition->title,
            'detail' => ($requisition->department?->name ?? 'No department').' · Headcount '.$requisition->headcount,
            'reason' => $requisition->description,
            'status' => $requisition->status,
            'status_label' => ucfirst($requisition->status),
            'submitted_at_label' => $requisition->created_at?->format('d M Y, h:i A'),
            'sort_at' => ($requisition->approved_at ?? $requisition->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewRequisition($requisition) ?? false),
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('job_requisition', (string) $requisition->id),
            'review_kind' => 'job_requisition',
            'review_target' => (string) $requisition->id,
            ...$this->reviewMeta($requisition),
        ];
    }

    private function canViewTeamExpense(User $user, Expense $expense): bool
    {
        if (! $user->canViewExpense($expense)) {
            return false;
        }

        return ! ($user->employee && (int) $user->employee->id === (int) $expense->employee_id);
    }

    private function canViewTeamExpenseGroup(User $user, ExpenseGroup $group): bool
    {
        if (! $user->canViewExpenseGroup($group)) {
            return false;
        }

        return ! ($user->employee && (int) $user->employee->id === (int) $group->employee_id);
    }

    private function requestShowUrl(string $category, string $id): string
    {
        if ($category === 'leave') {
            return route('web.leave.show', (int) $id);
        }

        return route('web.requests.show', ['category' => $category, 'id' => $id]);
    }

    private function reviewItem(User $user, string $kind, string $target, string $action, ?string $notes = null): void
    {
        if ($action === 'approve') {
            match ($kind) {
                'leave' => $this->leaveRequestService->approve($user, LeaveRequest::query()->findOrFail((int) $target)),
                'regularization' => $this->regularizationService->approve($user, AttendanceRegularizationRequest::query()->findOrFail((int) $target)),
                'regularization_batch' => $this->regularizationService->approveBatch($user, $target),
                'document' => $this->employeeDocumentService->approve($user, EmployeeDocument::query()->findOrFail((int) $target)),
                'payment_method' => $this->employeePaymentMethodService->approve($user, EmployeePaymentMethod::query()->findOrFail((int) $target)),
                'family_member' => $this->employeeFamilyMemberService->approve($user, EmployeeFamilyMember::query()->findOrFail((int) $target)),
                'personal_section' => $this->employeePersonalSectionService->approve($user, EmployeePersonalSection::query()->findOrFail((int) $target)),
                'compliance_field' => $this->employeeComplianceFieldService->approve($user, EmployeeComplianceField::query()->findOrFail((int) $target)),
                'expense' => $this->expenseService->approve($user, Expense::query()->findOrFail((int) $target)),
                'expense_group' => $this->expenseGroupService->approve($user, ExpenseGroup::query()->findOrFail((int) $target)),
                'job_requisition' => $this->hiringService->approveRequisition($user, JobRequisition::query()->findOrFail((int) $target)),
                default => throw ValidationException::withMessages(['item' => ['Unsupported request type.']]),
            };

            return;
        }

        match ($kind) {
            'leave' => $this->leaveRequestService->reject($user, LeaveRequest::query()->findOrFail((int) $target), (string) $notes),
            'regularization' => $this->regularizationService->reject($user, AttendanceRegularizationRequest::query()->findOrFail((int) $target), (string) $notes),
            'regularization_batch' => $this->regularizationService->rejectBatch($user, $target, (string) $notes),
            'document' => $this->employeeDocumentService->reject($user, EmployeeDocument::query()->findOrFail((int) $target), (string) $notes),
            'payment_method' => $this->employeePaymentMethodService->reject($user, EmployeePaymentMethod::query()->findOrFail((int) $target), (string) $notes),
            'family_member' => $this->employeeFamilyMemberService->reject($user, EmployeeFamilyMember::query()->findOrFail((int) $target), (string) $notes),
            'personal_section' => $this->employeePersonalSectionService->reject($user, EmployeePersonalSection::query()->findOrFail((int) $target), (string) $notes),
            'compliance_field' => $this->employeeComplianceFieldService->reject($user, EmployeeComplianceField::query()->findOrFail((int) $target), (string) $notes),
            'expense' => $this->expenseService->reject($user, Expense::query()->findOrFail((int) $target), (string) $notes),
            'expense_group' => $this->expenseGroupService->reject($user, ExpenseGroup::query()->findOrFail((int) $target), (string) $notes),
            'job_requisition' => $this->hiringService->rejectRequisition($user, JobRequisition::query()->findOrFail((int) $target), (string) $notes),
            default => throw ValidationException::withMessages(['item' => ['Unsupported request type.']]),
        };
    }

    private function profileUrlForEmployee(int $employeeId): string
    {
        $user = request()->user();

        if ($user?->employee && (int) $user->employee->id === $employeeId) {
            return route('web.profile');
        }

        return route('web.employees.profile.edit', ['employee' => $employeeId]);
    }
}
