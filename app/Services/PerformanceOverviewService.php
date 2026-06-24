<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PipPlan;
use App\Models\User;

class PerformanceOverviewService
{
    public function __construct(private PerformanceReviewCycleService $cycleService) {}

    public function summaryForUser(User $user): array
    {
        $companyId = $user->company_id;

        $activeCycles = PerformanceReviewCycle::query()
            ->where('company_id', $companyId)
            ->where('status', PerformanceReviewCycle::STATUS_ACTIVE)
            ->count();

        $pendingReviews = 0;
        if ($user->canReviewPerformance()) {
            $employee = $user->employee;
            if ($employee) {
                $pendingReviews = PerformanceReview::query()
                    ->whereHas('cycle', fn ($q) => $q->where('company_id', $companyId)->where('reviews_open', true))
                    ->where('reviewer_employee_id', $employee->id)
                    ->whereIn('status', ['not_started', 'in_progress'])
                    ->count();
            }
        }

        $goalsQuery = Goal::query()->where('company_id', $companyId)->where('status', Goal::STATUS_ACTIVE);
        if (! $user->canManagePerformance() && $user->employee) {
            $goalsQuery->where('employee_id', $user->employee->id);
        }
        $activeGoals = $goalsQuery->count();

        $pipsQuery = PipPlan::query()->where('company_id', $companyId)->where('status', PipPlan::STATUS_ACTIVE);
        if (! $user->canManagePips() && $user->employee) {
            $pipsQuery->where('employee_id', $user->employee->id);
        }
        $activePips = $pipsQuery->count();

        $myReviews = [];
        if ($user->canReviewPerformance()) {
            $myReviews = $this->cycleService->myReviews($user)
                ->take(5)
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'status' => $review->status,
                    'cycle_name' => $review->cycle?->name,
                    'reviewee_name' => $review->reviewee?->full_name,
                ])
                ->values()
                ->all();
        }

        return [
            'active_cycles' => $activeCycles,
            'pending_reviews' => $pendingReviews,
            'active_goals' => $activeGoals,
            'active_pips' => $activePips,
            'my_reviews' => $myReviews,
            'can_manage' => $user->canManagePerformance(),
            'can_review' => $user->canReviewPerformance(),
        ];
    }
}
