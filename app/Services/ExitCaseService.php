<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\ExitAssetReturnItem;
use App\Models\ExitCase;
use App\Models\ExitClearanceItem;
use App\Models\ExitSurveyResponse;
use App\Models\FullAndFinalSettlement;
use App\Models\ResignationRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExitCaseService
{
    public const CLEARANCE_DEPARTMENTS = [
        ['key' => 'manager', 'label' => 'Reporting Manager', 'sort' => 1],
        ['key' => 'hr', 'label' => 'HR Department', 'sort' => 2],
        ['key' => 'it', 'label' => 'IT / Systems', 'sort' => 3],
        ['key' => 'finance', 'label' => 'Finance & Accounts', 'sort' => 4],
        ['key' => 'admin', 'label' => 'Admin / Facilities', 'sort' => 5],
    ];

    public const SURVEY_QUESTIONS = [
        'What was your primary reason for leaving?',
        'How would you rate your overall experience working here? (1-5)',
        'What did you enjoy most about working here?',
        'What could the organization improve?',
        'Would you recommend this company to others? Why or why not?',
    ];

    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private EmployeeService $employeeService,
        private ActivityLogService $activityLogService,
        private WorkflowNotificationService $workflowNotificationService,
        private ExitSurveyQuestionService $exitSurveyQuestionService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ExitCase::query()
            ->with(['employee', 'resignationRequest', 'clearanceItems', 'assetReturnItems', 'surveyResponse', 'fullAndFinalSettlement'])
            ->where('company_id', $user->company_id)
            ->latest();

        if (! $user->canManageOffboarding()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                throw new AccessDeniedHttpException('No employee profile is linked to your account.');
            }

            $query->where('employee_id', $employee->id);
        } elseif (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function surveyQuestionsForCompany(int $companyId): array
    {
        return $this->exitSurveyQuestionService
            ->activeQuestionsForCompany($companyId)
            ->map(fn ($question) => [
                'id' => $question->id,
                'question' => $question->question,
                'type' => $question->type,
                'options' => $question->options ?? [],
                'is_required' => (bool) $question->is_required,
            ])
            ->values()
            ->all();
    }

    /** @deprecated Use surveyQuestionsForCompany() */
    public function surveyQuestions(): array
    {
        return collect(self::SURVEY_QUESTIONS)->values()->all();
    }

    public function createFromResignation(ResignationRequest $request): ExitCase
    {
        $request->loadMissing('employee');

        $exitCase = ExitCase::create([
            'company_id' => $request->company_id,
            'employee_id' => $request->employee_id,
            'resignation_request_id' => $request->id,
            'last_working_date' => $request->approved_last_working_date,
            'stage' => ExitCase::STAGE_CLEARANCE,
            'status' => ExitCase::STATUS_IN_PROGRESS,
        ]);

        foreach (self::CLEARANCE_DEPARTMENTS as $dept) {
            ExitClearanceItem::create([
                'exit_case_id' => $exitCase->id,
                'department_key' => $dept['key'],
                'label' => $dept['label'],
                'sort_order' => $dept['sort'],
                'status' => ExitClearanceItem::STATUS_PENDING,
            ]);
        }

        $assignedAssets = EmployeeAsset::query()
            ->with('assetType')
            ->where('employee_id', $request->employee_id)
            ->where('is_assigned', true)
            ->get();

        foreach ($assignedAssets as $asset) {
            ExitAssetReturnItem::create([
                'exit_case_id' => $exitCase->id,
                'asset_type_id' => $asset->asset_type_id,
                'asset_name' => $asset->assetType?->name ?? 'Asset',
                'status' => ExitAssetReturnItem::STATUS_PENDING,
            ]);
        }

        ExitSurveyResponse::create([
            'exit_case_id' => $exitCase->id,
            'employee_id' => $request->employee_id,
            'responses' => [],
        ]);

        FullAndFinalSettlement::create([
            'exit_case_id' => $exitCase->id,
            'employee_id' => $request->employee_id,
            'status' => FullAndFinalSettlement::STATUS_DRAFT,
        ]);

        $request->employee?->update([
            'last_working_date' => $request->approved_last_working_date,
            'exit_type' => 'resignation',
        ]);

        return $this->findExitCaseOrFail((int) $exitCase->id, [
            'clearanceItems',
            'assetReturnItems',
            'surveyResponse',
            'fullAndFinalSettlement',
        ]);
    }

    public function showForUser(User $user, ExitCase $exitCase): ExitCase
    {
        if (! $user->canViewExitCase($exitCase)) {
            throw new AccessDeniedHttpException('You are not allowed to view this exit case.');
        }

        return $exitCase->load([
            'employee',
            'resignationRequest.appliedBy',
            'resignationRequest.reviewedBy',
            'clearanceItems.reviewedBy',
            'assetReturnItems.receivedBy',
            'surveyResponse',
            'fullAndFinalSettlement.processedBy',
        ]);
    }

    public function reviewClearanceItem(User $user, ExitCase $exitCase, ExitClearanceItem $item, string $action, ?string $notes = null): ExitCase
    {
        $this->assertCanManageExitCase($user, $exitCase);

        if (! $user->canReviewClearanceItem($item)) {
            throw new AccessDeniedHttpException('You are not allowed to review this clearance item.');
        }

        if ($exitCase->status !== ExitCase::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['status' => 'This exit case is already closed.']);
        }

        if (! $item->isPending()) {
            throw ValidationException::withMessages(['status' => 'This clearance item has already been reviewed.']);
        }

        if ($action === 'reject' && ! filled(trim((string) $notes))) {
            throw ValidationException::withMessages(['notes' => ['Rejection reason is required.']]);
        }

        $item->update([
            'status' => $action === 'clear' ? ExitClearanceItem::STATUS_CLEARED : ExitClearanceItem::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => filled(trim((string) $notes)) ? trim((string) $notes) : null,
        ]);

        $this->syncStage($this->reloadForStageSync($exitCase));

        return $this->showForUser($user, $this->findExitCaseOrFail((int) $exitCase->id));
    }

    public function reviewClearanceItems(User $user, ExitCase $exitCase, array $itemIds, string $action, ?string $notes = null): ExitCase
    {
        $exitCaseId = (int) $exitCase->id;
        $exitCase->loadMissing('clearanceItems');

        foreach ($itemIds as $itemId) {
            $item = $exitCase->clearanceItems->firstWhere('id', (int) $itemId);

            if ($item) {
                $this->reviewClearanceItem($user, $exitCase, $item, $action, $notes);
            }
        }

        return $this->showForUser($user, $this->findExitCaseOrFail($exitCaseId));
    }

    public function markAssetReturned(User $user, ExitCase $exitCase, ExitAssetReturnItem $item, string $action, ?string $notes = null): ExitCase
    {
        $this->assertCanManageExitCase($user, $exitCase);

        if ($exitCase->status !== ExitCase::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['status' => 'This exit case is already closed.']);
        }

        if (! $item->isPending()) {
            throw ValidationException::withMessages(['status' => 'This asset has already been processed.']);
        }

        if (! in_array($action, ['returned', 'waived'], true)) {
            throw ValidationException::withMessages(['action' => ['Invalid asset return action.']]);
        }

        $item->update([
            'status' => $action === 'returned' ? ExitAssetReturnItem::STATUS_RETURNED : ExitAssetReturnItem::STATUS_WAIVED,
            'condition_notes' => filled(trim((string) $notes)) ? trim((string) $notes) : null,
            'received_by_user_id' => $user->id,
            'returned_at' => now(),
        ]);

        if ($action === 'returned') {
            EmployeeAsset::query()
                ->where('employee_id', $exitCase->employee_id)
                ->where('asset_type_id', $item->asset_type_id)
                ->update(['is_assigned' => false, 'description' => null]);
        }

        $this->syncStage($this->reloadForStageSync($exitCase));

        return $this->showForUser($user, $this->findExitCaseOrFail((int) $exitCase->id));
    }

    public function reviewAssetItems(User $user, ExitCase $exitCase, array $itemIds, string $action, ?string $notes = null): ExitCase
    {
        $exitCaseId = (int) $exitCase->id;
        $exitCase->loadMissing('assetReturnItems');

        foreach ($itemIds as $itemId) {
            $item = $exitCase->assetReturnItems->firstWhere('id', (int) $itemId);

            if ($item) {
                $this->markAssetReturned($user, $exitCase, $item, $action, $notes);
            }
        }

        return $this->showForUser($user, $this->findExitCaseOrFail($exitCaseId));
    }

    public function submitSurvey(User $user, ExitCase $exitCase, array $responses): ExitCase
    {
        if (! $user->isExitCaseOwner($exitCase)) {
            throw new AccessDeniedHttpException('Only the exiting employee can submit the exit survey.');
        }

        if ($exitCase->status !== ExitCase::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['status' => 'This exit case is already closed.']);
        }

        $survey = $exitCase->surveyResponse;

        if ($survey?->submitted_at) {
            throw ValidationException::withMessages(['responses' => ['Exit survey has already been submitted.']]);
        }

        $formatted = $this->exitSurveyQuestionService->formatSubmittedResponses(
            (int) $exitCase->company_id,
            $responses,
        );

        $survey->update([
            'responses' => $formatted,
            'submitted_at' => now(),
        ]);

        $this->syncStage($this->reloadForStageSync($exitCase));

        return $this->showForUser($user, $this->findExitCaseOrFail((int) $exitCase->id));
    }

    public function saveSettlement(User $user, ExitCase $exitCase, array $data): ExitCase
    {
        if (! $user->canManageFnfSettlement()) {
            throw new AccessDeniedHttpException('You are not allowed to manage F&F settlements.');
        }

        if ($exitCase->status !== ExitCase::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages(['status' => 'This exit case is already closed.']);
        }

        $settlement = $exitCase->fullAndFinalSettlement;

        if (! $settlement) {
            throw ValidationException::withMessages(['settlement' => ['Settlement record not found.']]);
        }

        if ($settlement->status === FullAndFinalSettlement::STATUS_PAID) {
            throw ValidationException::withMessages(['status' => ['Settlement has already been marked as paid.']]);
        }

        $leaveEncashment = (float) ($data['leave_encashment'] ?? 0);
        $pendingDues = (float) ($data['pending_dues'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $netPayable = round($leaveEncashment + $pendingDues - $deductions, 2);

        $settlement->update([
            'leave_encashment' => $leaveEncashment,
            'pending_dues' => $pendingDues,
            'deductions' => $deductions,
            'net_payable' => $netPayable,
            'settlement_notes' => isset($data['settlement_notes']) ? trim((string) $data['settlement_notes']) : null,
            'status' => FullAndFinalSettlement::STATUS_DRAFT,
        ]);

        return $this->showForUser($user, $this->findExitCaseOrFail((int) $exitCase->id));
    }

    public function approveSettlement(User $user, ExitCase $exitCase): ExitCase
    {
        if (! $user->canManageFnfSettlement()) {
            throw new AccessDeniedHttpException('You are not allowed to manage F&F settlements.');
        }

        $settlement = $exitCase->fullAndFinalSettlement;

        if (! $settlement || $settlement->status !== FullAndFinalSettlement::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => ['Settlement must be in draft status to approve.']]);
        }

        $settlement->update([
            'status' => FullAndFinalSettlement::STATUS_APPROVED,
            'processed_by_user_id' => $user->id,
            'processed_at' => now(),
        ]);

        $this->syncStage($this->reloadForStageSync($exitCase));

        return $this->showForUser($user, $this->findExitCaseOrFail((int) $exitCase->id));
    }

    public function markSettlementPaid(User $user, ExitCase $exitCase): ExitCase
    {
        if (! $user->canManageFnfSettlement()) {
            throw new AccessDeniedHttpException('You are not allowed to manage F&F settlements.');
        }

        $exitCase->loadMissing(['employee', 'fullAndFinalSettlement', 'clearanceItems', 'assetReturnItems', 'surveyResponse']);
        $settlement = $exitCase->fullAndFinalSettlement;

        if (! $settlement || $settlement->status !== FullAndFinalSettlement::STATUS_APPROVED) {
            throw ValidationException::withMessages(['status' => ['Settlement must be approved before marking as paid.']]);
        }

        $this->assertReadyForCompletion($exitCase);

        $exitCaseId = (int) $exitCase->id;
        $employee = $exitCase->employee;

        DB::transaction(function () use ($user, $exitCase, $settlement) {
            $settlement->update([
                'status' => FullAndFinalSettlement::STATUS_PAID,
                'processed_by_user_id' => $user->id,
                'processed_at' => now(),
            ]);

            $exitCase->update([
                'stage' => ExitCase::STAGE_COMPLETED,
                'status' => ExitCase::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        });

        if ($employee) {
            $this->employeeService->updateStatus($employee, 'inactive', $user);
        }

        $fresh = $this->showForUser($user, $this->findExitCaseOrFail($exitCaseId));
        $this->workflowNotificationService->notifyOffboardingCompleted($fresh, $user);

        return $fresh;
    }

    public function syncStage(ExitCase $exitCase): void
    {
        if ($exitCase->status !== ExitCase::STATUS_IN_PROGRESS) {
            return;
        }

        $exitCase->loadMissing(['clearanceItems', 'assetReturnItems', 'surveyResponse', 'fullAndFinalSettlement']);

        $clearanceDone = $exitCase->clearanceItems->every(
            fn (ExitClearanceItem $item) => $item->status === ExitClearanceItem::STATUS_CLEARED
        );

        $assetsDone = $exitCase->assetReturnItems->isEmpty()
            || $exitCase->assetReturnItems->every(
                fn (ExitAssetReturnItem $item) => in_array($item->status, [
                    ExitAssetReturnItem::STATUS_RETURNED,
                    ExitAssetReturnItem::STATUS_WAIVED,
                ], true)
            );

        $surveyDone = (bool) $exitCase->surveyResponse?->submitted_at;

        $fnf = $exitCase->fullAndFinalSettlement;
        $fnfApproved = $fnf && in_array($fnf->status, [
            FullAndFinalSettlement::STATUS_APPROVED,
            FullAndFinalSettlement::STATUS_PAID,
        ], true);

        $stage = ExitCase::STAGE_CLEARANCE;

        if ($clearanceDone) {
            $stage = ExitCase::STAGE_ASSET_RETURN;
        }

        if ($clearanceDone && $assetsDone) {
            $stage = ExitCase::STAGE_SURVEY;
        }

        if ($clearanceDone && $assetsDone && $surveyDone) {
            $stage = ExitCase::STAGE_FNF;
        }

        if ($clearanceDone && $assetsDone && $surveyDone && $fnfApproved) {
            $stage = ExitCase::STAGE_FNF;
        }

        $exitCase->update(['stage' => $stage]);
    }

    private function assertReadyForCompletion(ExitCase $exitCase): void
    {
        $exitCase->loadMissing(['clearanceItems', 'assetReturnItems', 'surveyResponse']);

        if ($exitCase->clearanceItems->contains(fn (ExitClearanceItem $item) => $item->status !== ExitClearanceItem::STATUS_CLEARED)) {
            throw ValidationException::withMessages(['clearance' => ['All clearance items must be cleared before completing offboarding.']]);
        }

        if ($exitCase->assetReturnItems->contains(fn (ExitAssetReturnItem $item) => $item->isPending())) {
            throw ValidationException::withMessages(['assets' => ['All assigned assets must be returned or waived.']]);
        }

        if (! $exitCase->surveyResponse?->submitted_at) {
            throw ValidationException::withMessages(['survey' => ['Exit survey must be submitted before completing offboarding.']]);
        }
    }

    private function assertCanManageExitCase(User $user, ExitCase $exitCase): void
    {
        if (! $user->canManageOffboarding() && ! $user->canReviewClearance()) {
            throw new AccessDeniedHttpException('You are not allowed to manage this exit case.');
        }

        if ((int) $exitCase->company_id !== (int) $user->company_id) {
            abort(404);
        }
    }

    private function findExitCaseOrFail(int $exitCaseId, array $with = []): ExitCase
    {
        $query = ExitCase::query();

        if ($with !== []) {
            $query->with($with);
        }

        return $query->findOrFail($exitCaseId);
    }

    private function reloadForStageSync(ExitCase $exitCase): ExitCase
    {
        return $this->findExitCaseOrFail((int) $exitCase->id, [
            'clearanceItems',
            'assetReturnItems',
            'surveyResponse',
            'fullAndFinalSettlement',
        ]);
    }
}
