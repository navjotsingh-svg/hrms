<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PipPlan;
use App\Models\User;

class PerformanceOverviewService
{
    public function __construct(
        private PerformanceReviewCycleService $cycleService,
        private PerformanceFeedbackRequestService $feedbackRequestService,
        private EmployeeAccessService $employeeAccessService,
    ) {}

    public function summaryForUser(User $user): array
    {
        $companyId = $user->company_id;
        $employee = $this->employeeAccessService->linkedEmployee($user);
        $canParticipate = $user->canParticipateInPerformance();

        $activeCyclesQuery = PerformanceReviewCycle::query()
            ->where('company_id', $companyId)
            ->where('status', PerformanceReviewCycle::STATUS_ACTIVE);

        if (! $user->canManagePerformance() && $employee) {
            $activeCyclesQuery->whereHas('reviews', function ($query) use ($employee) {
                $query->where('reviewee_employee_id', $employee->id)
                    ->orWhere('reviewer_employee_id', $employee->id);
            });
        }

        $activeCycles = $activeCyclesQuery->count();

        $pendingReviews = 0;
        if ($employee && $canParticipate) {
            $pendingReviews = PerformanceReview::query()
                ->whereHas('cycle', fn ($q) => $q->where('company_id', $companyId)->where('reviews_open', true))
                ->where('reviewer_employee_id', $employee->id)
                ->whereIn('status', ['not_started', 'in_progress'])
                ->count();
        }

        $goalsQuery = Goal::query()->where('company_id', $companyId)->where('status', Goal::STATUS_ACTIVE);
        if (! $user->canManagePerformance() && $employee) {
            $goalsQuery->where('employee_id', $employee->id);
        }
        $activeGoals = $goalsQuery->count();

        $pipsQuery = PipPlan::query()->where('company_id', $companyId)->where('status', PipPlan::STATUS_ACTIVE);
        if (! $user->canManagePips() && $employee) {
            $pipsQuery->where('employee_id', $employee->id);
        }
        $activePips = $pipsQuery->count();

        $myReviews = [];
        $reviewsAboutMe = [];
        if ($employee && $canParticipate) {
            $myReviews = $this->cycleService->myReviews($user)
                ->map(fn ($review) => $this->formatReviewRow($review, $employee->id, 'reviewer'))
                ->values()
                ->all();

            $reviewsAboutMe = $this->cycleService->reviewsAboutMe($user)
                ->map(fn ($review) => $this->formatReviewRow($review, $employee->id, 'reviewee'))
                ->values()
                ->all();
        }

        $pendingFeedback = $this->feedbackRequestService->pendingCountForUser($user);
        $myFeedbackRequests = $this->feedbackRequestService->recentPendingForUser($user)
            ->map(fn ($item) => [
                'id' => $item->id,
                'status' => $item->status,
                'form_name' => $item->feedbackForm?->name,
                'subject_name' => $item->subject?->full_name,
                'due_date' => $item->due_date?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'active_cycles' => $activeCycles,
            'pending_reviews' => $pendingReviews,
            'pending_feedback' => $pendingFeedback,
            'active_goals' => $activeGoals,
            'active_pips' => $activePips,
            'my_reviews' => $myReviews,
            'reviews_about_me' => $reviewsAboutMe,
            'my_feedback_requests' => $myFeedbackRequests,
            'can_manage' => $user->canManagePerformance(),
            'can_review' => $user->canReviewPerformance(),
        ];
    }

    private function formatReviewRow(PerformanceReview $review, int $employeeId, string $perspective): array
    {
        return [
            'id' => $review->id,
            'status' => $review->status,
            'cycle_name' => $review->cycle?->name,
            'reviewee_name' => $review->reviewee?->full_name,
            'reviewer_name' => $review->reviewer?->full_name,
            'overall_rating' => $review->overall_rating,
            'can_submit' => $perspective === 'reviewer'
                && (int) $review->reviewer_employee_id === $employeeId
                && $review->status !== PerformanceReview::STATUS_SUBMITTED
                && (bool) $review->cycle?->reviews_open,
        ];
    }
}
