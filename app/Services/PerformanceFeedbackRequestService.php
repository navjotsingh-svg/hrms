<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PerformanceFeedbackForm;
use App\Models\PerformanceFeedbackRequest;
use App\Models\PerformanceFeedbackRequestAnswer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PerformanceFeedbackRequestService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForAdmin(User $user, array $filters = []): Collection
    {
        $this->assertManage($user);

        $query = PerformanceFeedbackRequest::query()
            ->with(['feedbackForm', 'subject', 'reviewer', 'requestedBy'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['subject_employee_id'])) {
            $query->where('subject_employee_id', $filters['subject_employee_id']);
        }

        return $query->get();
    }

    public function listMine(User $user): Collection
    {
        if (! $user->canReviewPerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to complete feedback requests.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return collect();
        }

        return PerformanceFeedbackRequest::query()
            ->with(['feedbackForm', 'subject'])
            ->where('company_id', $user->company_id)
            ->where('reviewer_employee_id', $employee->id)
            ->whereIn('status', [
                PerformanceFeedbackRequest::STATUS_PENDING,
                PerformanceFeedbackRequest::STATUS_IN_PROGRESS,
                PerformanceFeedbackRequest::STATUS_SUBMITTED,
            ])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get();
    }

    public function listReceived(User $user): Collection
    {
        if (! $user->canParticipateInPerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to view feedback.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return collect();
        }

        return PerformanceFeedbackRequest::query()
            ->with(['feedbackForm', 'reviewer', 'reviewerUser'])
            ->where('company_id', $user->company_id)
            ->where('subject_employee_id', $employee->id)
            ->where('status', PerformanceFeedbackRequest::STATUS_SUBMITTED)
            ->orderByDesc('submitted_at')
            ->get();
    }

    public function store(User $user, array $data): Collection
    {
        $this->assertManage($user);

        $form = PerformanceFeedbackForm::query()
            ->with('questions')
            ->where('company_id', $user->company_id)
            ->where('id', $data['feedback_form_id'])
            ->first();

        if (! $form) {
            throw ValidationException::withMessages(['feedback_form_id' => 'Feedback form not found.']);
        }

        if ($form->status !== PerformanceFeedbackForm::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['feedback_form_id' => 'Only active feedback forms can be assigned.']);
        }

        if ($form->questions->isEmpty()) {
            throw ValidationException::withMessages(['feedback_form_id' => 'This form has no questions.']);
        }

        $subject = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('id', $data['subject_employee_id'])
            ->first();

        if (! $subject) {
            throw ValidationException::withMessages(['subject_employee_id' => 'Subject employee not found.']);
        }

        $reviewerIds = array_values(array_unique(array_filter($data['reviewer_employee_ids'] ?? [])));

        if ($reviewerIds === []) {
            throw ValidationException::withMessages(['reviewer_employee_ids' => 'Select at least one reviewer.']);
        }

        $reviewers = Employee::query()
            ->where('company_id', $user->company_id)
            ->whereIn('id', $reviewerIds)
            ->get()
            ->keyBy('id');

        if ($reviewers->count() !== count($reviewerIds)) {
            throw ValidationException::withMessages(['reviewer_employee_ids' => 'One or more reviewers were not found.']);
        }

        return DB::transaction(function () use ($user, $form, $subject, $reviewerIds, $reviewers, $data) {
            $created = collect();

            foreach ($reviewerIds as $reviewerId) {
                if ((int) $reviewerId === (int) $subject->id) {
                    throw ValidationException::withMessages([
                        'reviewer_employee_ids' => 'A reviewer cannot be the same as the feedback subject.',
                    ]);
                }

                $duplicate = PerformanceFeedbackRequest::query()
                    ->where('company_id', $user->company_id)
                    ->where('feedback_form_id', $form->id)
                    ->where('subject_employee_id', $subject->id)
                    ->where('reviewer_employee_id', $reviewerId)
                    ->whereIn('status', [
                        PerformanceFeedbackRequest::STATUS_PENDING,
                        PerformanceFeedbackRequest::STATUS_IN_PROGRESS,
                    ])
                    ->exists();

                if ($duplicate) {
                    $reviewer = $reviewers->get($reviewerId);

                    throw ValidationException::withMessages([
                        'reviewer_employee_ids' => "A pending request already exists for {$reviewer?->full_name}.",
                    ]);
                }

                $created->push(PerformanceFeedbackRequest::create([
                    'company_id' => $user->company_id,
                    'feedback_form_id' => $form->id,
                    'subject_employee_id' => $subject->id,
                    'reviewer_employee_id' => $reviewerId,
                    'requested_by_user_id' => $user->id,
                    'status' => PerformanceFeedbackRequest::STATUS_PENDING,
                    'context_notes' => $data['context_notes'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                ]));
            }

            return $created->each(fn ($request) => $request->load(['feedbackForm', 'subject', 'reviewer', 'requestedBy']));
        });
    }

    public function resolve(User $user, PerformanceFeedbackRequest $request): PerformanceFeedbackRequest
    {
        if ((int) $request->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Feedback request not found.');
        }

        return $request;
    }

    public function resolveForView(User $user, PerformanceFeedbackRequest $request): PerformanceFeedbackRequest
    {
        $this->resolve($user, $request);

        if ($user->canManagePerformance()) {
            return $request->load(['feedbackForm.questions', 'subject', 'reviewer', 'answers.formQuestion']);
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($employee && (int) $request->reviewer_employee_id === (int) $employee->id && $user->canReviewPerformance()) {
            return $request->load(['feedbackForm.questions', 'subject', 'reviewer', 'answers.formQuestion']);
        }

        if ($employee
            && (int) $request->subject_employee_id === (int) $employee->id
            && $user->canParticipateInPerformance()
            && $request->status === PerformanceFeedbackRequest::STATUS_SUBMITTED
        ) {
            return $request->load(['feedbackForm.questions', 'subject', 'reviewer', 'answers.formQuestion']);
        }

        throw new AccessDeniedHttpException('You do not have permission to view this feedback request.');
    }

    public function submit(User $user, PerformanceFeedbackRequest $request, array $data): PerformanceFeedbackRequest
    {
        $this->resolve($user, $request);
        $this->assertCanFill($user, $request);

        if ($request->status === PerformanceFeedbackRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'This feedback has already been submitted.']);
        }

        if ($request->status === PerformanceFeedbackRequest::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['status' => 'This feedback request was cancelled.']);
        }

        $request->loadMissing('feedbackForm.questions');
        $questionIds = $request->feedbackForm->questions->pluck('id')->all();

        return DB::transaction(function () use ($user, $request, $data, $questionIds) {
            $request->update(['status' => PerformanceFeedbackRequest::STATUS_IN_PROGRESS]);

            foreach ($data['answers'] as $answer) {
                if (! in_array((int) $answer['form_question_id'], $questionIds, true)) {
                    throw ValidationException::withMessages([
                        'answers' => 'One or more questions do not belong to this feedback form.',
                    ]);
                }

                PerformanceFeedbackRequestAnswer::updateOrCreate(
                    [
                        'request_id' => $request->id,
                        'form_question_id' => $answer['form_question_id'],
                    ],
                    [
                        'rating' => $answer['rating'] ?? null,
                        'response_text' => $answer['response_text'] ?? null,
                    ]
                );
            }

            $overall = $this->calculateWeightedRating($request->fresh(['answers.formQuestion']));

            $request->update([
                'status' => PerformanceFeedbackRequest::STATUS_SUBMITTED,
                'overall_rating' => $overall,
                'submitted_at' => now(),
                'reviewer_user_id' => $user->id,
            ]);

            return $request->fresh(['feedbackForm.questions', 'subject', 'reviewer', 'answers.formQuestion']);
        });
    }

    public function cancel(User $user, PerformanceFeedbackRequest $request): PerformanceFeedbackRequest
    {
        $this->resolve($user, $request);
        $this->assertManage($user);

        if ($request->status === PerformanceFeedbackRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Submitted feedback cannot be cancelled.']);
        }

        $request->update(['status' => PerformanceFeedbackRequest::STATUS_CANCELLED]);

        return $request->fresh(['feedbackForm', 'subject', 'reviewer']);
    }

    public function pendingCountForUser(User $user): int
    {
        if (! $user->canReviewPerformance()) {
            return 0;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return 0;
        }

        return PerformanceFeedbackRequest::query()
            ->where('company_id', $user->company_id)
            ->where('reviewer_employee_id', $employee->id)
            ->whereIn('status', [
                PerformanceFeedbackRequest::STATUS_PENDING,
                PerformanceFeedbackRequest::STATUS_IN_PROGRESS,
            ])
            ->count();
    }

    public function recentPendingForUser(User $user, int $limit = 5): Collection
    {
        if (! $user->canReviewPerformance()) {
            return collect();
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return collect();
        }

        return PerformanceFeedbackRequest::query()
            ->with(['feedbackForm', 'subject'])
            ->where('company_id', $user->company_id)
            ->where('reviewer_employee_id', $employee->id)
            ->whereIn('status', [
                PerformanceFeedbackRequest::STATUS_PENDING,
                PerformanceFeedbackRequest::STATUS_IN_PROGRESS,
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function calculateWeightedRating(PerformanceFeedbackRequest $request): ?float
    {
        $answers = $request->answers->filter(fn ($a) => $a->rating !== null);

        if ($answers->isEmpty()) {
            return null;
        }

        $totalWeight = $answers->sum(fn ($a) => (float) ($a->formQuestion?->weight ?? 1)) ?: 1;

        $weighted = $answers->sum(function ($answer) use ($totalWeight) {
            $weight = (float) ($answer->formQuestion?->weight ?? 1);

            return ((int) $answer->rating) * ($weight / $totalWeight);
        });

        return round($weighted, 2);
    }

    private function assertCanFill(User $user, PerformanceFeedbackRequest $request): void
    {
        if (! $user->canReviewPerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to submit feedback.');
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee || (int) $request->reviewer_employee_id !== (int) $employee->id) {
            throw new AccessDeniedHttpException('You are not assigned as the reviewer for this feedback request.');
        }
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to manage feedback requests.');
        }
    }
}
