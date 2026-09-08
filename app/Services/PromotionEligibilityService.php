<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PipPlan;
use App\Models\PromotionNomination;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PromotionEligibilityService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    /** @return array<string, string> */
    public function criteriaLabels(): array
    {
        return config('promotion_criteria.criteria_labels', []);
    }

    /** @return array<string, mixed> */
    public function thresholds(): array
    {
        return [
            'min_tenure_months' => (int) config('promotion_criteria.min_tenure_months', 12),
            'min_performance_rating' => (float) config('promotion_criteria.min_performance_rating', 3.5),
            'require_submitted_review' => (bool) config('promotion_criteria.require_submitted_review', true),
            'exclude_active_pip' => (bool) config('promotion_criteria.exclude_active_pip', true),
            'exclude_open_nomination' => (bool) config('promotion_criteria.exclude_open_nomination', true),
            'require_active_employee' => (bool) config('promotion_criteria.require_active_employee', true),
            'require_probation_cleared' => (bool) config('promotion_criteria.require_probation_cleared', true),
        ];
    }

    /** @return array{eligible: bool, score: float, criteria: array<int, array<string, mixed>>, metrics: array<string, mixed>} */
    public function evaluate(Employee $employee): array
    {
        $employee->loadMissing(['department', 'manager']);

        $thresholds = $this->thresholds();
        $latestReview = $this->latestSubmittedReview($employee);
        $tenureMonths = $this->tenureMonths($employee);
        $hasOpenNomination = PromotionNomination::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                PromotionNomination::STATUS_DRAFT,
                PromotionNomination::STATUS_NOMINATED,
            ])
            ->exists();
        $hasActivePip = PipPlan::query()
            ->where('employee_id', $employee->id)
            ->where('status', PipPlan::STATUS_ACTIVE)
            ->exists();

        $criteria = [];

        if ($thresholds['require_active_employee']) {
            $passed = $employee->status === 'active';
            $criteria[] = $this->criterion('active_status', $passed, $passed ? 'Active employee' : 'Employee status is '.$employee->status);
        }

        if ($thresholds['require_probation_cleared']) {
            $passed = ! $employee->isOnProbation();
            $criteria[] = $this->criterion('probation_cleared', $passed, $passed ? 'Probation cleared' : 'Employee is still on probation');
        }

        $minTenure = $thresholds['min_tenure_months'];
        if ($minTenure > 0) {
            $passed = $tenureMonths !== null && $tenureMonths >= $minTenure;
            $criteria[] = $this->criterion(
                'minimum_tenure',
                $passed,
                $tenureMonths === null
                    ? 'Joining date not recorded'
                    : sprintf('%s months tenure (requires %s+)', $tenureMonths, $minTenure),
            );
        }

        if ($thresholds['exclude_active_pip']) {
            $passed = ! $hasActivePip;
            $criteria[] = $this->criterion('no_active_pip', $passed, $passed ? 'No active PIP' : 'Employee has an active performance improvement plan');
        }

        if ($thresholds['require_submitted_review']) {
            $passed = $latestReview !== null;
            $criteria[] = $this->criterion(
                'review_completed',
                $passed,
                $passed
                    ? 'Review submitted for '.($latestReview->cycle?->name ?? 'latest cycle')
                    : 'No submitted performance review found',
            );
        }

        $minRating = $thresholds['min_performance_rating'];
        if ($minRating > 0) {
            $rating = $latestReview?->overall_rating;
            $passed = $rating !== null && (float) $rating >= $minRating;
            $criteria[] = $this->criterion(
                'performance_rating',
                $passed,
                $rating === null
                    ? 'No performance rating available'
                    : sprintf('Rating %.2f (requires %.2f+)', (float) $rating, $minRating),
            );
        }

        if ($thresholds['exclude_open_nomination']) {
            $passed = ! $hasOpenNomination;
            $criteria[] = $this->criterion('no_open_nomination', $passed, $passed ? 'No open recommendation' : 'A draft or submitted recommendation already exists');
        }

        $passedCount = collect($criteria)->where('passed', true)->count();
        $total = max(count($criteria), 1);
        $eligible = $passedCount === count($criteria) && count($criteria) > 0;

        return [
            'eligible' => $eligible,
            'score' => round(($passedCount / $total) * 100, 1),
            'criteria' => $criteria,
            'metrics' => [
                'tenure_months' => $tenureMonths,
                'latest_rating' => $latestReview?->overall_rating,
                'latest_review_cycle' => $latestReview?->cycle?->name,
                'has_open_nomination' => $hasOpenNomination,
                'has_active_pip' => $hasActivePip,
            ],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function recommendationsForUser(User $user, array $filters = []): Collection
    {
        $query = Employee::query()
            ->with(['department', 'manager'])
            ->where('company_id', $user->company_id)
            ->orderBy('first_name')
            ->orderBy('last_name');

        if (! $user->canManagePerformance()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                return collect();
            }

            $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
            $visibleIds = array_values(array_unique([$employee->id, ...$subordinateIds]));
            $query->whereIn('id', $visibleIds);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        $onlyEligible = filter_var($filters['eligible_only'] ?? true, FILTER_VALIDATE_BOOL);

        return $query->get()
            ->map(function (Employee $employee) {
                $evaluation = $this->evaluate($employee);

                return [
                    'employee' => [
                        'id' => $employee->id,
                        'full_name' => $employee->full_name,
                        'employee_code' => $employee->employee_code,
                        'designation' => $employee->designation,
                        'department' => $employee->department?->name,
                        'manager' => $employee->manager?->full_name,
                        'joining_date' => $employee->joining_date?->toDateString(),
                        'status' => $employee->status,
                    ],
                    'eligible' => $evaluation['eligible'],
                    'score' => $evaluation['score'],
                    'criteria' => $evaluation['criteria'],
                    'metrics' => $evaluation['metrics'],
                ];
            })
            ->when($onlyEligible, fn (Collection $items) => $items->where('eligible', true))
            ->sortByDesc('score')
            ->values();
    }

    public function assertEligible(Employee $employee): void
    {
        $evaluation = $this->evaluate($employee);

        if ($evaluation['eligible']) {
            return;
        }

        $failed = collect($evaluation['criteria'])
            ->where('passed', false)
            ->pluck('detail')
            ->implode('; ');

        throw ValidationException::withMessages([
            'employee_id' => ['Employee does not meet promotion recommendation criteria: '.$failed],
        ]);
    }

    private function latestSubmittedReview(Employee $employee): ?PerformanceReview
    {
        return PerformanceReview::query()
            ->with('cycle')
            ->where('reviewee_employee_id', $employee->id)
            ->where('status', PerformanceReview::STATUS_SUBMITTED)
            ->whereHas('cycle', fn ($query) => $query
                ->where('company_id', $employee->company_id)
                ->whereIn('status', [
                    PerformanceReviewCycle::STATUS_ACTIVE,
                    PerformanceReviewCycle::STATUS_CLOSED,
                ]))
            ->orderByDesc('submitted_at')
            ->first();
    }

    private function tenureMonths(Employee $employee): ?int
    {
        if (! $employee->joining_date) {
            return null;
        }

        return (int) $employee->joining_date->diffInMonths(now());
    }

    /** @return array<string, mixed> */
    private function criterion(string $key, bool $passed, string $detail): array
    {
        return [
            'key' => $key,
            'label' => config('promotion_criteria.criteria_labels.'.$key, $key),
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
