<?php

namespace App\Services;

use App\Models\AssetRequest;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\EmployeeComplianceField;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFamilyMember;
use App\Models\EmployeePaymentMethod;
use App\Models\EmployeePaymentMethodProof;
use App\Models\EmployeeProfilePhoto;
use App\Models\EmployeePersonalSection;
use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\JobRequisition;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WfhRequest;
use App\Support\ArrayPaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestHubService
{
    public function __construct(
        private LeaveRequestService $leaveRequestService,
        private WfhRequestService $wfhRequestService,
        private AssetRequestService $assetRequestService,
        private AttendanceRegularizationService $regularizationService,
        private EmployeeDocumentService $employeeDocumentService,
        private EmployeePaymentMethodService $employeePaymentMethodService,
        private EmployeeProfilePhotoService $employeeProfilePhotoService,
        private EmployeeFamilyMemberService $employeeFamilyMemberService,
        private EmployeePersonalSectionService $employeePersonalSectionService,
        private EmployeeComplianceFieldService $employeeComplianceFieldService,
        private ExpenseService $expenseService,
        private ExpenseGroupService $expenseGroupService,
        private HiringService $hiringService,
        private EmployeeAccessService $employeeAccessService,
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
        $paginated = ArrayPaginator::paginate($this->pendingForUser($user), $page, $perPage);

        return [
            'requests' => $paginated['items'],
            'pagination' => $paginated['pagination'],
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
            || $user->canApproveWfh()
            || $user->canApproveAssets()
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

        if ($user->canApproveWfh()) {
            $this->wfhRequestService->pendingForReviewer($user)->each(function (WfhRequest $request) use ($items) {
                $items->push($this->normalizeWfh($request, true));
            });
        }

        if ($user->canApproveAssets()) {
            $this->assetRequestService->pendingForReviewer($user)->each(function (AssetRequest $request) use ($items) {
                $items->push($this->normalizeAssetRequest($request, true));
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

            $this->employeeProfilePhotoService->pendingForReviewer($user)->each(function (EmployeeProfilePhoto $photo) use ($items, $user) {
                $items->push($this->normalizeProfilePhoto($photo, $user->canReviewProfilePhoto($photo)));
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

        $statuses = $status ? [$status] : ['pending', 'approved', 'rejected', 'cancelled'];
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
                ->with(['employee', 'submittedBy', 'reviewedBy', 'proofs'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeePaymentMethod $method) => $this->canViewTeamPaymentMethod($user, $method))
                ->each(fn (EmployeePaymentMethod $method) => $items->push($this->normalizePaymentMethod($method, false)));

            EmployeeProfilePhoto::query()
                ->with(['employee', 'submittedBy', 'reviewedBy'])
                ->where('company_id', $user->company_id)
                ->whereIn('status', $statuses)
                ->latest('reviewed_at')
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->filter(fn (EmployeeProfilePhoto $photo) => $this->canViewTeamProfilePhoto($user, $photo))
                ->each(fn (EmployeeProfilePhoto $photo) => $items->push($this->normalizeProfilePhoto($photo, false)));

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
        $employeeId = app(EmployeeAccessService::class)->linkedEmployee($user)?->id;

        if ($user->canViewLeaveRequests()) {
            $leaveQuery = LeaveRequest::query()
                ->with(['employee', 'leaveType', 'appliedBy'])
                ->where('company_id', $user->company_id)
                ->latest();

            if ($employeeId) {
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

            if ($employeeId) {
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

        if ($user->canApplyWfh() && $employeeId) {
            $wfhQuery = WfhRequest::query()
                ->with(['employee', 'appliedBy'])
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employeeId)
                ->latest();

            if ($status) {
                $wfhQuery->where('status', $status);
            }

            $wfhQuery->limit(50)->get()->each(function (WfhRequest $request) use ($user, $items) {
                $items->push($this->normalizeWfh($request, false, $user));
            });
        }

        if ($user->canApplyAssets() && $employeeId) {
            $assetQuery = AssetRequest::query()
                ->with(['employee', 'appliedBy', 'items'])
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employeeId)
                ->latest();

            if ($status) {
                $assetQuery->where('status', $status);
            }

            $assetQuery->limit(50)->get()->each(function (AssetRequest $request) use ($user, $items) {
                $items->push($this->normalizeAssetRequest($request, false, $user));
            });
        }

        return $items
            ->sortByDesc('sort_at')
            ->values()
            ->all();
    }

    public function showForUser(User $user, string $category, string $entityId): array
    {
        return match ($category) {
            'document' => $this->showDocument($user, (int) $entityId),
            'payment_method' => $this->showPaymentMethod($user, (int) $entityId),
            'profile_photo' => $this->showProfilePhoto($user, (int) $entityId),
            'family_member' => $this->showFamilyMember($user, (int) $entityId),
            'personal_section' => $this->showPersonalSection($user, (int) $entityId),
            'compliance_field' => $this->showComplianceField($user, (int) $entityId),
            'job_requisition' => $this->showJobRequisition($user, (int) $entityId),
            default => throw new NotFoundHttpException('Request not found.'),
        };
    }

    /** @param  array<int, array<string, mixed>>  $requests */
    public function filterByDateRange(array $requests, ?string $dateFrom, ?string $dateTo): array
    {
        if (! $dateFrom && ! $dateTo) {
            return $requests;
        }

        return array_values(array_filter($requests, function (array $request) use ($dateFrom, $dateTo) {
            $submittedOn = $request['submitted_on'] ?? null;

            if (! $submittedOn) {
                return false;
            }

            if ($dateFrom && $submittedOn < $dateFrom) {
                return false;
            }

            if ($dateTo && $submittedOn > $dateTo) {
                return false;
            }

            return true;
        }));
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
            ->with(['employee', 'submittedBy', 'proofs'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeePaymentMethod $method) => $items->push($this->normalizePaymentMethod($method, false)));

        EmployeeProfilePhoto::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(50)
            ->get()
            ->each(fn (EmployeeProfilePhoto $photo) => $items->push($this->normalizeProfilePhoto($photo, false)));

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
        $request->loadMissing('employee');

        if (! $this->canViewTeamEmployeeRequest($user, $request->employee)) {
            return false;
        }

        return $user->canViewLeaveRequest($request);
    }

    private function canViewTeamRegularization(User $user, AttendanceRegularizationRequest $request): bool
    {
        $request->loadMissing('employee');

        if ((int) $request->company_id !== (int) $user->company_id) {
            return false;
        }

        return $user->canManageRegularization();
    }

    private function canViewTeamDocument(User $user, EmployeeDocument $document): bool
    {
        if ((int) $document->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($document->uploadedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        if (! $user->canReviewEmployeeDocuments()) {
            return false;
        }

        $document->loadMissing('employee');

        return $this->canViewTeamEmployeeRequest($user, $document->employee);
    }

    private function canViewTeamPaymentMethod(User $user, EmployeePaymentMethod $method): bool
    {
        if ((int) $method->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($method->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        if (! $user->canReviewEmployeeDocuments()) {
            return false;
        }

        $method->loadMissing('employee');

        return $this->canViewTeamEmployeeRequest($user, $method->employee);
    }

    private function canViewTeamProfilePhoto(User $user, EmployeeProfilePhoto $photo): bool
    {
        if ((int) $photo->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($photo->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        if (! $user->canReviewEmployeeDocuments()) {
            return false;
        }

        $photo->loadMissing('employee');

        return $this->canViewTeamEmployeeRequest($user, $photo->employee);
    }

    private function canViewTeamFamilyMember(User $user, EmployeeFamilyMember $member): bool
    {
        if ((int) $member->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($member->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        if (! $user->canReviewEmployeeDocuments()) {
            return false;
        }

        $member->loadMissing('employee');

        return $this->canViewTeamEmployeeRequest($user, $member->employee);
    }

    private function canViewTeamPersonalSection(User $user, EmployeePersonalSection $section): bool
    {
        if ((int) $section->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($section->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        if (! $user->canReviewEmployeeDocuments()) {
            return false;
        }

        $section->loadMissing('employee');

        return $this->canViewTeamEmployeeRequest($user, $section->employee);
    }

    private function canViewTeamComplianceField(User $user, EmployeeComplianceField $field): bool
    {
        if ((int) $field->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($field->submittedBy?->isHrManager()) {
            return $user->isCompanyAdmin();
        }

        if (! $user->canReviewEmployeeDocuments()) {
            return false;
        }

        $field->loadMissing('employee');

        return $this->canViewTeamEmployeeRequest($user, $field->employee);
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
                    'reviewed_at_label' => $request->reviewed_at?->labelStack(),
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
                ...app(\App\Services\AttendanceRegularizationService::class)->formatOriginalPunchFields($request),
                'requested_punch_in_label' => $request->requested_punch_in?->format('h:i A'),
                'requested_punch_out_label' => $request->requested_punch_out?->format('h:i A'),
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
            'reviewed_at_label' => $reviewedAt?->labelStack(),
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
            'employee_id' => $request->employee_id,
            'subject' => $request->leaveType?->name ?? 'Leave Request',
            'detail' => trim(($dateSummary ?: '').($request->total_days ? ' · '.$request->total_days.' day(s)' : '')),
            'reason' => $request->reason,
            'status' => $request->status,
            'status_label' => ucfirst($request->status),
            'submitted_at_label' => $request->created_at?->labelStack(),
            'sort_at' => ($request->reviewed_at ?? $request->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewLeaveRequest($request) ?? false),
            'can_cancel' => ! $forApproval && ($viewer?->canCancelLeaveRequest($request) ?? false),
            'view_url' => $this->requestShowUrl('leave', (string) $request->id),
            'review_kind' => 'leave',
            'review_target' => (string) $request->id,
            ...$this->reviewMeta($request),
            ...$this->submissionMeta($request),
        ];
    }

    private function normalizeWfh(WfhRequest $request, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();
        $dateSummary = $request->from_date?->equalTo($request->to_date)
            ? $request->from_date->format('d M Y')
            : ($request->from_date?->format('d M Y').' - '.$request->to_date?->format('d M Y'));

        return [
            'key' => 'wfh:'.$request->id,
            'category' => 'wfh',
            'category_label' => 'Work From Home',
            'entity_id' => $request->id,
            'batch_id' => null,
            'requester_name' => $request->employee?->full_name ?? 'Employee',
            'requester_code' => $request->employee?->employee_code,
            'employee_id' => $request->employee_id,
            'subject' => 'Work From Home',
            'detail' => trim(($dateSummary ?: '').($request->total_days ? ' · '.$request->total_days.' day(s)' : '')),
            'reason' => $request->reason,
            'status' => $request->status,
            'status_label' => ucfirst($request->status),
            'submitted_at_label' => $request->created_at?->labelStack(),
            'sort_at' => ($request->reviewed_at ?? $request->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewWfhRequest($request) ?? false),
            'can_cancel' => ! $forApproval && ($viewer?->canCancelWfhRequest($request) ?? false),
            'view_url' => $this->requestShowUrl('wfh', (string) $request->id),
            'review_kind' => 'wfh',
            'review_target' => (string) $request->id,
            ...$this->reviewMeta($request),
            ...$this->submissionMeta($request),
        ];
    }

    private function normalizeAssetRequest(AssetRequest $request, bool $forApproval, ?User $viewer = null): array
    {
        $viewer ??= request()->user();
        $request->loadMissing('items.assetType');
        $assetName = $request->assetNamesLabel();

        return [
            'key' => 'asset:'.$request->id,
            'category' => 'asset',
            'category_label' => 'Asset Request',
            'entity_id' => $request->id,
            'batch_id' => null,
            'requester_name' => $request->employee?->full_name ?? 'Employee',
            'requester_code' => $request->employee?->employee_code,
            'employee_id' => $request->employee_id,
            'subject' => $assetName,
            'detail' => $assetName,
            'reason' => $request->reason,
            'status' => $request->status,
            'status_label' => $request->statusLabel(),
            'submitted_at_label' => $request->created_at?->labelStack(),
            'sort_at' => ($request->reviewed_at ?? $request->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewAssetRequest($request) ?? false),
            'can_cancel' => ! $forApproval && ($viewer?->canCancelAssetRequest($request) ?? false),
            'view_url' => $this->requestShowUrl('asset', (string) $request->id),
            'review_kind' => 'asset',
            'review_target' => (string) $request->id,
            ...$this->reviewMeta($request),
            ...$this->submissionMeta($request),
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
            'employee_id' => $group['employee']['id'] ?? null,
            'subject' => $dayCount > 1 ? "{$dayCount} day(s)" : ($group['dates'][0]['attendance_date_label'] ?? 'Attendance'),
            'detail' => collect([
                $dateLabels ?: null,
                ($group['original_punch_in_label'] ?? null) || ($group['original_punch_out_label'] ?? null)
                    ? 'Login / Logout '.collect([
                        ! empty($group['original_punch_in_label']) ? $group['original_punch_in_label'] : null,
                        ! empty($group['original_punch_out_label']) ? $group['original_punch_out_label'] : null,
                    ])->filter()->implode(' · ')
                    : null,
                ($group['requested_punch_in_label'] ?? null) || ($group['requested_punch_out_label'] ?? null)
                    ? 'Requested '.collect([
                        ! empty($group['requested_punch_in_label']) ? $group['requested_punch_in_label'] : null,
                        ! empty($group['requested_punch_out_label']) ? $group['requested_punch_out_label'] : null,
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
        $dayDetails = collect($group['dates'] ?? [])
            ->map(function (array $day) {
                $date = $day['attendance_date_short_label'] ?? $day['attendance_date_label'] ?? null;
                $original = collect([
                    ! empty($day['original_punch_in_label']) ? $day['original_punch_in_label'] : null,
                    ! empty($day['original_punch_out_label']) ? $day['original_punch_out_label'] : null,
                ])->filter()->implode(' · ');
                $requested = collect([
                    ! empty($day['requested_punch_in_label']) ? $day['requested_punch_in_label'] : null,
                    ! empty($day['requested_punch_out_label']) ? $day['requested_punch_out_label'] : null,
                ])->filter()->implode(' · ');

                return collect([
                    $date,
                    $original ? "Login / Logout {$original}" : null,
                    $requested ? "Requested {$requested}" : null,
                ])->filter()->implode(' · ');
            })
            ->filter()
            ->implode(' | ');

        return [
            'key' => 'regularization-reviewed:'.($group['batch_id'] ?? implode('-', $group['request_ids'] ?? [])),
            'category' => 'regularization',
            'category_label' => 'Regularization',
            'entity_id' => $group['request_ids'][0] ?? null,
            'batch_id' => $group['batch_id'] ?? null,
            'requester_name' => $group['employee']['full_name'] ?? 'Employee',
            'requester_code' => $group['employee']['employee_code'] ?? null,
            'employee_id' => $group['employee']['id'] ?? null,
            'subject' => $dayCount > 1 ? "{$dayCount} day(s)" : ($group['dates'][0]['attendance_date_label'] ?? 'Attendance'),
            'detail' => $dayDetails ?: ($dateLabels ?: null),
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
        $originalFields = app(\App\Services\AttendanceRegularizationService::class)->formatOriginalPunchFields($request);

        return [
            'key' => 'regularization:'.$request->id,
            'category' => 'regularization',
            'category_label' => 'Regularization',
            'entity_id' => $request->id,
            'batch_id' => $request->batch_id,
            'requester_name' => $request->employee?->full_name ?? 'Employee',
            'requester_code' => $request->employee?->employee_code,
            'employee_id' => $request->employee_id,
            'subject' => $request->attendance_date?->format('D, d M Y') ?? 'Attendance',
            'detail' => collect([
                $request->attendance_date?->format('D, d M Y'),
                ($originalFields['original_punch_in_label'] ?? null) || ($originalFields['original_punch_out_label'] ?? null)
                    ? 'Login / Logout '.collect([
                        ! empty($originalFields['original_punch_in_label']) ? $originalFields['original_punch_in_label'] : null,
                        ! empty($originalFields['original_punch_out_label']) ? $originalFields['original_punch_out_label'] : null,
                    ])->filter()->implode(' · ')
                    : null,
                ($request->requested_punch_in?->format('h:i A') || $request->requested_punch_out?->format('h:i A'))
                    ? 'Requested '.collect([
                        $request->requested_punch_in?->format('h:i A') ? $request->requested_punch_in->format('h:i A') : null,
                        $request->requested_punch_out?->format('h:i A') ? $request->requested_punch_out->format('h:i A') : null,
                    ])->filter()->implode(' · ')
                    : null,
            ])->filter()->implode(' · '),
            'reason' => $request->reason,
            'status' => $request->status,
            'status_label' => ucfirst($request->status),
            'submitted_at_label' => $request->created_at?->labelStack(),
            'sort_at' => ($request->reviewed_at ?? $request->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewRegularizationRequest($request) ?? false),
            'can_cancel' => ! $forApproval && ($viewer?->canCancelRegularizationRequest($request) ?? false),
            'view_url' => $this->requestShowUrl('regularization', (string) $request->id),
            'review_kind' => 'regularization',
            'review_target' => (string) $request->id,
            ...$this->reviewMeta($request),
            ...$this->submissionMeta($request),
        ];
    }

    private function normalizeDocument(EmployeeDocument $document, bool $canReview): array
    {
        $documentTypeName = $document->documentType?->name ?? 'Document Update';

        return [
            'key' => 'document:'.$document->id,
            'category' => 'document',
            'category_label' => $documentTypeName,
            'entity_id' => $document->id,
            'batch_id' => null,
            'requester_name' => $document->employee?->full_name ?? 'Employee',
            'requester_code' => $document->employee?->employee_code,
            'employee_id' => $document->employee_id,
            'subject' => $documentTypeName,
            'detail' => $document->original_name,
            'reason' => $document->notes,
            'status' => $document->status,
            'status_label' => ucfirst($document->status),
            'submitted_at_label' => $document->created_at?->labelStack(),
            'sort_at' => ($document->reviewed_at ?? $document->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('document', (string) $document->id),
            'review_kind' => 'document',
            'review_target' => (string) $document->id,
            'document_type_name' => $documentTypeName,
            'attachments' => $this->documentAttachments($document),
            ...$this->reviewMeta($document),
            ...$this->submissionMeta($document),
        ];
    }

    private function normalizePaymentMethod(EmployeePaymentMethod $method, bool $canReview): array
    {
        $paymentLabel = 'Bank details';

        return [
            'key' => 'payment_method:'.$method->id,
            'category' => 'payment_method',
            'category_label' => $paymentLabel,
            'entity_id' => $method->id,
            'batch_id' => null,
            'requester_name' => $method->employee?->full_name ?? 'Employee',
            'requester_code' => $method->employee?->employee_code,
            'employee_id' => $method->employee_id,
            'subject' => $paymentLabel,
            'detail' => $method->bank_name ?: $method->account_holder_name,
            'reason' => $method->notes,
            'status' => $method->status,
            'status_label' => ucfirst($method->status),
            'submitted_at_label' => ($method->submitted_at ?? $method->created_at)?->labelStack(),
            'sort_at' => ($method->reviewed_at ?? $method->submitted_at ?? $method->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('payment_method', (string) $method->id),
            'review_kind' => 'payment_method',
            'review_target' => (string) $method->id,
            'attachments' => $this->paymentMethodAttachments($method),
            ...$this->reviewMeta($method),
            ...$this->submissionMeta($method),
        ];
    }

    private function normalizeProfilePhoto(EmployeeProfilePhoto $photo, bool $canReview): array
    {
        return [
            'key' => 'profile_photo:'.$photo->id,
            'category' => 'profile_photo',
            'category_label' => 'Profile Photo',
            'entity_id' => $photo->id,
            'batch_id' => null,
            'requester_name' => $photo->employee?->full_name ?? 'Employee',
            'requester_code' => $photo->employee?->employee_code,
            'employee_id' => $photo->employee_id,
            'subject' => 'Profile Photo',
            'detail' => null,
            'reason' => $photo->notes,
            'status' => $photo->status,
            'status_label' => ucfirst($photo->status),
            'submitted_at_label' => ($photo->submitted_at ?? $photo->created_at)?->labelStack(),
            'sort_at' => ($photo->reviewed_at ?? $photo->submitted_at ?? $photo->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('profile_photo', (string) $photo->id),
            'review_kind' => 'profile_photo',
            'review_target' => (string) $photo->id,
            'attachments' => [[
                'id' => $photo->id,
                'label' => 'View profile photo',
                'file_url' => '/api/v1/employee-profile-photos/'.$photo->id.'/download',
                'download_url' => '/api/v1/employee-profile-photos/'.$photo->id.'/download',
                'mime_type' => $photo->mime_type,
            ]],
            ...$this->reviewMeta($photo),
            ...$this->submissionMeta($photo),
        ];
    }

    private function showProfilePhoto(User $user, int $id): array
    {
        $photo = EmployeeProfilePhoto::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $photo) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->assertCanViewProfileItem($user, $photo, fn (User $viewer, EmployeeProfilePhoto $item) => $viewer->canViewProfilePhotoRequest($item));

        $item = $this->normalizeProfilePhoto($photo, $user->canReviewProfilePhoto($photo));

        return $item;
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
            'employee_id' => $member->employee_id,
            'subject' => $member->name ?? 'Family Member',
            'detail' => $member->relation,
            'reason' => $member->notes,
            'status' => $member->status,
            'status_label' => ucfirst($member->status),
            'submitted_at_label' => ($member->submitted_at ?? $member->created_at)?->labelStack(),
            'sort_at' => ($member->reviewed_at ?? $member->submitted_at ?? $member->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('family_member', (string) $member->id),
            'review_kind' => 'family_member',
            'review_target' => (string) $member->id,
            ...$this->reviewMeta($member),
            ...$this->submissionMeta($member),
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
            'employee_id' => $section->employee_id,
            'subject' => EmployeePersonalSection::SECTION_LABELS[$section->section_type] ?? ucfirst(str_replace('_', ' ', $section->section_type ?? 'Personal Section')),
            'detail' => 'Profile update',
            'reason' => $section->notes,
            'status' => $section->status,
            'status_label' => ucfirst($section->status),
            'submitted_at_label' => ($section->submitted_at ?? $section->created_at)?->labelStack(),
            'sort_at' => ($section->reviewed_at ?? $section->submitted_at ?? $section->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('personal_section', (string) $section->id),
            'review_kind' => 'personal_section',
            'review_target' => (string) $section->id,
            ...$this->reviewMeta($section),
            ...$this->submissionMeta($section),
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
            'employee_id' => $field->employee_id,
            'subject' => strtoupper($field->field_type ?? 'Compliance'),
            'detail' => $field->value,
            'reason' => $field->notes,
            'status' => $field->status,
            'status_label' => ucfirst($field->status),
            'submitted_at_label' => ($field->submitted_at ?? $field->created_at)?->labelStack(),
            'sort_at' => ($field->reviewed_at ?? $field->submitted_at ?? $field->created_at)?->timestamp ?? 0,
            'can_review' => $canReview,
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('compliance_field', (string) $field->id),
            'review_kind' => 'compliance_field',
            'review_target' => (string) $field->id,
            ...$this->reviewMeta($field),
            ...$this->submissionMeta($field),
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
            'employee_id' => $expense->employee_id,
            'subject' => $expense->expenseType?->name ?? 'Expense Claim',
            'detail' => ($expense->expense_date?->format('d M Y') ?? '').' · ₹'.number_format((float) $expense->amount, 2),
            'reason' => $expense->description,
            'status' => $expense->status,
            'status_label' => ucfirst($expense->status),
            'submitted_at_label' => $expense->created_at?->labelStack(),
            'sort_at' => ($expense->reviewed_at ?? $expense->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewExpense($expense) ?? false),
            'can_cancel' => ! $forApproval && $expense->is_independent && in_array($expense->status, ['draft', 'pending'], true)
                && $viewer?->employee && (int) $viewer->employee->id === (int) $expense->employee_id,
            'view_url' => $this->requestShowUrl('expense', (string) $expense->id),
            'review_kind' => 'expense',
            'review_target' => (string) $expense->id,
            ...$this->reviewMeta($expense),
            ...$this->submissionMeta($expense),
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
            'employee_id' => $group->employee_id,
            'subject' => $group->name,
            'detail' => ($group->from_date?->format('d M Y') ?? '').' - '.($group->to_date?->format('d M Y') ?? '')
                .' · ₹'.number_format($group->totalAmount(), 2),
            'reason' => $group->description,
            'status' => $group->status,
            'status_label' => ucfirst($group->status),
            'submitted_at_label' => $group->created_at?->labelStack(),
            'sort_at' => ($group->reviewed_at ?? $group->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewExpenseGroup($group) ?? false),
            'can_cancel' => ! $forApproval && in_array($group->status, ['draft', 'pending'], true)
                && $viewer?->employee && (int) $viewer->employee->id === (int) $group->employee_id,
            'view_url' => $this->requestShowUrl('expense_group', (string) $group->id),
            'review_kind' => 'expense_group',
            'review_target' => (string) $group->id,
            ...$this->reviewMeta($group),
            ...$this->submissionMeta($group),
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
            'employee_id' => $requisition->requestedBy?->employee?->id,
            'subject' => $requisition->title,
            'detail' => ($requisition->department?->name ?? 'No department').' · Headcount '.$requisition->headcount,
            'reason' => $requisition->description,
            'status' => $requisition->status,
            'status_label' => ucfirst($requisition->status),
            'submitted_at_label' => $requisition->created_at?->labelStack(),
            'sort_at' => ($requisition->approved_at ?? $requisition->created_at)?->timestamp ?? 0,
            'can_review' => $forApproval && ($viewer?->canReviewRequisition($requisition) ?? false),
            'can_cancel' => false,
            'view_url' => $this->requestShowUrl('job_requisition', (string) $requisition->id),
            'review_kind' => 'job_requisition',
            'review_target' => (string) $requisition->id,
            ...$this->reviewMeta($requisition),
            ...$this->submissionMeta($requisition),
        ];
    }

    private function canViewTeamExpense(User $user, Expense $expense): bool
    {
        $expense->loadMissing('employee');

        if (! $this->canViewTeamEmployeeRequest($user, $expense->employee)) {
            return false;
        }

        return $user->canViewExpense($expense);
    }

    private function canViewTeamExpenseGroup(User $user, ExpenseGroup $group): bool
    {
        $group->loadMissing('employee');

        if (! $this->canViewTeamEmployeeRequest($user, $group->employee)) {
            return false;
        }

        return $user->canViewExpenseGroup($group);
    }

    private function canViewTeamEmployeeRequest(User $user, ?Employee $employee): bool
    {
        if (! $employee) {
            return false;
        }

        if ((int) $employee->company_id !== (int) $user->company_id) {
            return false;
        }

        $linkedEmployee = $this->employeeAccessService->linkedEmployee($user);

        if ($linkedEmployee && (int) $linkedEmployee->id === (int) $employee->id) {
            return false;
        }

        if ($user->isCompanyAdmin() || $user->isHrManager()) {
            return true;
        }

        return in_array(
            (int) $employee->id,
            $this->employeeAccessService->subordinateIdsForUser($user),
            true,
        );
    }

    private function requestShowUrl(string $category, string $id): string
    {
        if ($category === 'leave') {
            return route('web.leave.show', (int) $id);
        }

        if ($category === 'wfh') {
            return route('web.wfh.show', (int) $id);
        }

        if ($category === 'asset') {
            return route('web.asset-requests.show', (int) $id);
        }

        return route('web.requests.show', ['category' => $category, 'id' => $id]);
    }

    private function reviewItem(User $user, string $kind, string $target, string $action, ?string $notes = null): void
    {
        if ($action === 'approve') {
            match ($kind) {
                'leave' => $this->leaveRequestService->approve($user, LeaveRequest::query()->findOrFail((int) $target), $notes),
                'wfh' => $this->wfhRequestService->approve($user, WfhRequest::query()->findOrFail((int) $target), $notes),
                'asset' => $this->assetRequestService->approve($user, AssetRequest::query()->findOrFail((int) $target), $notes),
                'regularization' => $this->regularizationService->approve($user, AttendanceRegularizationRequest::query()->findOrFail((int) $target), $notes),
                'regularization_batch' => $this->regularizationService->approveBatch($user, $target, $notes),
                'document' => $this->employeeDocumentService->approve($user, EmployeeDocument::query()->findOrFail((int) $target), $notes),
                'payment_method' => $this->employeePaymentMethodService->approve($user, EmployeePaymentMethod::query()->findOrFail((int) $target), $notes),
                'profile_photo' => $this->employeeProfilePhotoService->approve($user, EmployeeProfilePhoto::query()->findOrFail((int) $target), $notes),
                'family_member' => $this->employeeFamilyMemberService->approve($user, EmployeeFamilyMember::query()->findOrFail((int) $target), $notes),
                'personal_section' => $this->employeePersonalSectionService->approve($user, EmployeePersonalSection::query()->findOrFail((int) $target), $notes),
                'compliance_field' => $this->employeeComplianceFieldService->approve($user, EmployeeComplianceField::query()->findOrFail((int) $target), $notes),
                'expense' => $this->expenseService->approve($user, Expense::query()->findOrFail((int) $target), $notes),
                'expense_group' => $this->expenseGroupService->approve($user, ExpenseGroup::query()->findOrFail((int) $target), $notes),
                'job_requisition' => $this->hiringService->approveRequisition($user, JobRequisition::query()->findOrFail((int) $target), $notes),
                default => throw ValidationException::withMessages(['item' => ['Unsupported request type.']]),
            };

            return;
        }

        match ($kind) {
            'leave' => $this->leaveRequestService->reject($user, LeaveRequest::query()->findOrFail((int) $target), (string) $notes),
            'wfh' => $this->wfhRequestService->reject($user, WfhRequest::query()->findOrFail((int) $target), (string) $notes),
            'asset' => $this->assetRequestService->reject($user, AssetRequest::query()->findOrFail((int) $target), (string) $notes),
            'regularization' => $this->regularizationService->reject($user, AttendanceRegularizationRequest::query()->findOrFail((int) $target), (string) $notes),
            'regularization_batch' => $this->regularizationService->rejectBatch($user, $target, (string) $notes),
            'document' => $this->employeeDocumentService->reject($user, EmployeeDocument::query()->findOrFail((int) $target), (string) $notes),
            'payment_method' => $this->employeePaymentMethodService->reject($user, EmployeePaymentMethod::query()->findOrFail((int) $target), (string) $notes),
            'profile_photo' => $this->employeeProfilePhotoService->reject($user, EmployeeProfilePhoto::query()->findOrFail((int) $target), (string) $notes),
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

    private function submissionMeta(object $model): array
    {
        $submittedAt = $model->submitted_at ?? $model->created_at ?? null;

        return [
            'submitted_on' => $submittedAt?->toDateString(),
        ];
    }

    private function showDocument(User $user, int $id): array
    {
        $document = EmployeeDocument::query()
            ->with(['documentType', 'employee', 'uploadedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $document) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->assertCanViewProfileItem($user, $document, fn (User $viewer, EmployeeDocument $item) => $viewer->canViewDocumentRequest($item));

        return $this->enrichDocumentDetail(
            $this->normalizeDocument($document, $user->canReviewDocument($document)),
            $document,
        );
    }

    private function showPaymentMethod(User $user, int $id): array
    {
        $method = EmployeePaymentMethod::query()
            ->with(['employee', 'submittedBy', 'reviewedBy', 'proofs'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $method) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->assertCanViewProfileItem($user, $method, fn (User $viewer, EmployeePaymentMethod $item) => $viewer->canViewPaymentMethodRequest($item));

        return $this->enrichPaymentMethodDetail(
            $this->normalizePaymentMethod($method, $user->canReviewPaymentMethod($method)),
            $method,
        );
    }

    private function showFamilyMember(User $user, int $id): array
    {
        $member = EmployeeFamilyMember::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $member) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->assertCanViewProfileItem($user, $member, fn (User $viewer, EmployeeFamilyMember $item) => $viewer->canViewFamilyMemberRequest($item));

        $item = $this->normalizeFamilyMember($member, $user->canReviewFamilyMember($member));
        $item['fields'] = array_values(array_filter([
            ['label' => 'Name', 'value' => $member->name],
            ['label' => 'Relation', 'value' => $member->relation],
            ['label' => 'Phone', 'value' => $member->phone],
            ['label' => 'Date of Birth', 'value' => $member->date_of_birth?->format('d M Y')],
        ], fn (array $field) => filled($field['value'] ?? null)));

        return $item;
    }

    private function showPersonalSection(User $user, int $id): array
    {
        $section = EmployeePersonalSection::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $section) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->assertCanViewProfileItem($user, $section, fn (User $viewer, EmployeePersonalSection $item) => $viewer->canViewPersonalSectionRequest($item));

        $item = $this->normalizePersonalSection($section, $user->canReviewPersonalSection($section));
        $item['fields'] = $this->personalSectionFields($section);

        return $item;
    }

    private function showComplianceField(User $user, int $id): array
    {
        $field = EmployeeComplianceField::query()
            ->with(['employee', 'submittedBy', 'reviewedBy'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $field) {
            throw new NotFoundHttpException('Request not found.');
        }

        $this->assertCanViewProfileItem($user, $field, fn (User $viewer, EmployeeComplianceField $item) => $viewer->canViewComplianceFieldRequest($item));

        $item = $this->normalizeComplianceField($field, $user->canReviewComplianceField($field));
        $item['fields'] = [
            ['label' => strtoupper($field->field_type ?? 'Value'), 'value' => $field->value],
        ];

        return $item;
    }

    private function showJobRequisition(User $user, int $id): array
    {
        $requisition = JobRequisition::query()
            ->with(['department', 'requestedBy', 'approver'])
            ->where('company_id', $user->company_id)
            ->find($id);

        if (! $requisition) {
            throw new NotFoundHttpException('Request not found.');
        }

        $isRequester = (int) $requisition->requested_by_user_id === (int) $user->id;
        $canView = $isRequester || $user->canViewHiring() || $user->canApproveRequisitions();

        if (! $canView) {
            throw new AccessDeniedHttpException('You are not allowed to view this request.');
        }

        $forApproval = $requisition->status === 'pending' && $user->canReviewRequisition($requisition);

        return $this->normalizeJobRequisition($requisition, $forApproval, $user);
    }

    private function assertCanViewProfileItem(User $user, object $entity, callable $canReview): void
    {
        if ((int) $entity->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Request not found.');
        }

        $isOwner = $user->employee && (int) $user->employee->id === (int) $entity->employee_id;

        if ($isOwner || $canReview($user, $entity)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to view this request.');
    }

    /** @return array<int, array<string, mixed>> */
    private function documentAttachments(EmployeeDocument $document): array
    {
        if (! $document->file_path) {
            return [];
        }

        return [[
            'id' => $document->id,
            'label' => $document->original_name,
            'file_url' => $document->fileUrl(),
            'download_url' => '/api/v1/employee-documents/'.$document->id.'/download',
            'mime_type' => $document->mime_type,
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private function paymentMethodAttachments(EmployeePaymentMethod $method): array
    {
        $method->loadMissing('proofs');

        return $method->proofs
            ->map(fn (EmployeePaymentMethodProof $proof) => [
                'id' => $proof->id,
                'label' => $proof->original_name,
                'file_url' => $proof->fileUrl(),
                'download_url' => '/api/v1/employee-payment-method-proofs/'.$proof->id.'/download',
                'mime_type' => $proof->mime_type,
            ])
            ->values()
            ->all();
    }

    private function enrichDocumentDetail(array $item, EmployeeDocument $document): array
    {
        $item['document_type_name'] = $document->documentType?->name;
        $item['attachments'] = $this->documentAttachments($document);

        return $item;
    }

    private function enrichPaymentMethodDetail(array $item, EmployeePaymentMethod $method): array
    {
        $item['attachments'] = $this->paymentMethodAttachments($method);
        $item['fields'] = array_values(array_filter([
            ['label' => 'Payment Mode', 'value' => ucfirst(str_replace('_', ' ', $method->payment_mode ?? ''))],
            ['label' => 'Bank Name', 'value' => $method->bank_name],
            ['label' => 'Branch', 'value' => $method->bank_branch],
            ['label' => 'Account Holder', 'value' => $method->account_holder_name],
            ['label' => 'Account Number', 'value' => $method->account_number],
            ['label' => 'IFSC Code', 'value' => $method->ifsc_code],
        ], fn (array $field) => filled($field['value'] ?? null)));

        return $item;
    }

    /** @return array<int, array<string, string|null>> */
    private function personalSectionFields(EmployeePersonalSection $section): array
    {
        $payload = $section->payload ?? [];

        if ($section->section_type === 'address') {
            $permanent = $payload['permanent'] ?? [];
            $sameAsPermanent = (bool) ($payload['same_as_permanent'] ?? false);
            $temporary = $sameAsPermanent ? $permanent : ($payload['temporary'] ?? []);

            return array_values(array_filter([
                ['label' => 'Permanent Address', 'value' => $this->formatAddress($permanent)],
                ['label' => 'Temporary Address', 'value' => $sameAsPermanent ? 'Same as permanent' : $this->formatAddress($temporary)],
            ], fn (array $field) => filled($field['value'] ?? null)));
        }

        if ($section->section_type === 'emergency_contact') {
            return array_values(array_filter([
                ['label' => 'Name', 'value' => $payload['name'] ?? null],
                ['label' => 'Relation', 'value' => $payload['relation'] ?? null],
                ['label' => 'Phone', 'value' => $payload['phone'] ?? null],
            ], fn (array $field) => filled($field['value'] ?? null)));
        }

        return [];
    }

    /** @param  array<string, mixed>  $address */
    private function formatAddress(array $address): ?string
    {
        $parts = array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['postal_code'] ?? null,
            $address['country'] ?? null,
        ], fn ($part) => filled($part));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
