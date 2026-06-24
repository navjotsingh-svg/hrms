<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewAnswer;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewPair;
use App\Models\PerformanceReviewQuestion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PerformanceReviewCycleService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForUser(User $user): Collection
    {
        return PerformanceReviewCycle::query()
            ->withCount(['reviews', 'pairs'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('period_start')
            ->get();
    }

    public function store(User $user, array $data): PerformanceReviewCycle
    {
        $this->assertManage($user);

        return DB::transaction(function () use ($user, $data) {
            $cycle = PerformanceReviewCycle::create([
                'company_id' => $user->company_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => PerformanceReviewCycle::STATUS_DRAFT,
                'reviews_open' => false,
                'created_by_user_id' => $user->id,
            ]);

            $this->syncQuestions($cycle, $data['questions'] ?? []);
            $this->syncPairs($cycle, $data['pairs'] ?? []);

            return $cycle->fresh(['questions', 'pairs.reviewee', 'pairs.reviewer']);
        });
    }

    public function update(User $user, PerformanceReviewCycle $cycle, array $data): PerformanceReviewCycle
    {
        $this->resolveCycle($user, $cycle);
        $this->assertManage($user);

        if ($cycle->status === PerformanceReviewCycle::STATUS_CLOSED) {
            throw ValidationException::withMessages(['status' => 'Closed review cycles cannot be edited.']);
        }

        return DB::transaction(function () use ($cycle, $data) {
            $cycle->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
            ]);

            if (isset($data['questions'])) {
                $this->syncQuestions($cycle, $data['questions']);
            }

            if (isset($data['pairs'])) {
                $this->syncPairs($cycle, $data['pairs']);
            }

            return $cycle->fresh(['questions', 'pairs.reviewee', 'pairs.reviewer']);
        });
    }

    public function activate(User $user, PerformanceReviewCycle $cycle): PerformanceReviewCycle
    {
        $this->resolveCycle($user, $cycle);
        $this->assertManage($user);

        $cycle->update(['status' => PerformanceReviewCycle::STATUS_ACTIVE]);

        return $cycle;
    }

    public function close(User $user, PerformanceReviewCycle $cycle): PerformanceReviewCycle
    {
        $this->resolveCycle($user, $cycle);
        $this->assertManage($user);

        $cycle->update([
            'status' => PerformanceReviewCycle::STATUS_CLOSED,
            'reviews_open' => false,
        ]);

        return $cycle;
    }

    public function toggleReviewsOpen(User $user, PerformanceReviewCycle $cycle, bool $open): PerformanceReviewCycle
    {
        $this->resolveCycle($user, $cycle);
        $this->assertManage($user);

        if ($cycle->status !== PerformanceReviewCycle::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => 'Reviews can only be opened on active cycles.']);
        }

        $cycle->update(['reviews_open' => $open]);

        return $cycle;
    }

    public function progress(User $user, PerformanceReviewCycle $cycle): array
    {
        $this->resolveCycle($user, $cycle);
        $this->assertManage($user);

        $reviews = PerformanceReview::query()->where('cycle_id', $cycle->id)->get();
        $total = $reviews->count();
        $submitted = $reviews->where('status', PerformanceReview::STATUS_SUBMITTED)->count();
        $inProgress = $reviews->where('status', PerformanceReview::STATUS_IN_PROGRESS)->count();

        return [
            'total_reviews' => $total,
            'submitted' => $submitted,
            'in_progress' => $inProgress,
            'not_started' => $total - $submitted - $inProgress,
            'completion_percent' => $total > 0 ? round(($submitted / $total) * 100, 1) : 0,
        ];
    }

    public function sendReminders(User $user, PerformanceReviewCycle $cycle): int
    {
        $this->resolveCycle($user, $cycle);
        $this->assertManage($user);

        $pending = PerformanceReview::query()
            ->with(['reviewer.user', 'reviewee', 'cycle'])
            ->where('cycle_id', $cycle->id)
            ->whereIn('status', [PerformanceReview::STATUS_NOT_STARTED, PerformanceReview::STATUS_IN_PROGRESS])
            ->get();

        $sent = 0;

        foreach ($pending as $review) {
            $email = $review->reviewer?->email ?: $review->reviewerUser?->email;
            if (! $email) {
                continue;
            }

            try {
                Mail::raw(
                    "Reminder: Please complete your performance review for {$review->reviewee?->full_name} in cycle \"{$cycle->name}\".",
                    fn ($message) => $message->to($email)->subject("Performance review reminder — {$cycle->name}")
                );
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    public function myReviews(User $user): Collection
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            return collect();
        }

        return PerformanceReview::query()
            ->with(['cycle', 'reviewee', 'answers.question'])
            ->where('reviewer_employee_id', $employee->id)
            ->whereHas('cycle', fn ($q) => $q->where('company_id', $user->company_id))
            ->orderByDesc('updated_at')
            ->get();
    }

    public function submitReview(User $user, PerformanceReview $review, array $data): PerformanceReview
    {
        $this->assertCanFillReview($user, $review);

        if (! $review->cycle?->reviews_open) {
            throw ValidationException::withMessages(['reviews' => 'Reviews are not open for this cycle.']);
        }

        return DB::transaction(function () use ($user, $review, $data) {
            $review->update(['status' => PerformanceReview::STATUS_IN_PROGRESS]);

            foreach ($data['answers'] as $answer) {
                PerformanceReviewAnswer::updateOrCreate(
                    [
                        'review_id' => $review->id,
                        'question_id' => $answer['question_id'],
                    ],
                    [
                        'rating' => $answer['rating'] ?? null,
                        'comment' => $answer['comment'] ?? null,
                    ]
                );
            }

            $overall = $this->calculateWeightedRating($review->fresh('answers.question'));

            $review->update([
                'status' => PerformanceReview::STATUS_SUBMITTED,
                'overall_rating' => $overall,
                'summary_notes' => $data['summary_notes'] ?? null,
                'submitted_at' => now(),
                'reviewer_user_id' => $user->id,
            ]);

            return $review->fresh(['cycle', 'reviewee', 'answers.question']);
        });
    }

    public function resolveCycle(User $user, PerformanceReviewCycle $cycle): PerformanceReviewCycle
    {
        if ((int) $cycle->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Review cycle not found.');
        }

        return $cycle;
    }

    public function resolveReview(User $user, PerformanceReview $review): PerformanceReview
    {
        if ((int) $review->cycle?->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Review not found.');
        }

        return $review->load(['cycle.questions', 'reviewee', 'answers.question']);
    }

    private function syncQuestions(PerformanceReviewCycle $cycle, array $questions): void
    {
        $cycle->questions()->delete();

        foreach (array_values($questions) as $index => $question) {
            if (empty($question['question'])) {
                continue;
            }

            PerformanceReviewQuestion::create([
                'cycle_id' => $cycle->id,
                'question' => $question['question'],
                'weight' => $question['weight'] ?? 1,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function syncPairs(PerformanceReviewCycle $cycle, array $pairs): void
    {
        $cycle->pairs()->each(function (PerformanceReviewPair $pair) {
            $pair->review?->delete();
            $pair->delete();
        });

        foreach ($pairs as $pairData) {
            if (empty($pairData['reviewee_employee_id'])) {
                continue;
            }

            $pair = PerformanceReviewPair::create([
                'cycle_id' => $cycle->id,
                'reviewee_employee_id' => $pairData['reviewee_employee_id'],
                'reviewer_employee_id' => $pairData['reviewer_employee_id'] ?? null,
                'relationship' => $pairData['relationship'] ?? PerformanceReviewPair::RELATIONSHIP_MANAGER,
            ]);

            PerformanceReview::create([
                'cycle_id' => $cycle->id,
                'pair_id' => $pair->id,
                'reviewee_employee_id' => $pair->reviewee_employee_id,
                'reviewer_employee_id' => $pair->reviewer_employee_id,
                'status' => PerformanceReview::STATUS_NOT_STARTED,
            ]);
        }
    }

    private function calculateWeightedRating(PerformanceReview $review): ?float
    {
        $answers = $review->answers->filter(fn ($a) => $a->rating !== null);

        if ($answers->isEmpty()) {
            return null;
        }

        $totalWeight = $answers->sum(fn ($a) => (float) ($a->question?->weight ?? 1)) ?: 1;

        $weighted = $answers->sum(function ($answer) use ($totalWeight) {
            $weight = (float) ($answer->question?->weight ?? 1);

            return ((int) $answer->rating) * ($weight / $totalWeight);
        });

        return round($weighted, 2);
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You are not allowed to manage performance review cycles.');
        }
    }

    private function assertCanFillReview(User $user, PerformanceReview $review): void
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee || (int) $review->reviewer_employee_id !== (int) $employee->id) {
            throw new AccessDeniedHttpException('You are not assigned as the reviewer for this review.');
        }

        if ($review->status === PerformanceReview::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'This review has already been submitted.']);
        }
    }
}
