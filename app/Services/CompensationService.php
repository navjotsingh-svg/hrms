<?php

namespace App\Services;

use App\Models\CompensationBand;
use App\Models\CompensationRecommendation;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CompensationService
{
    public function listBands(User $user, array $filters = []): LengthAwarePaginator
    {
        $this->assertManage($user);

        $query = CompensationBand::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name');

        if (($filters['active_only'] ?? false) === true || ($filters['active_only'] ?? '') === '1') {
            $query->where('is_active', true);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('grade', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeBand(User $user, array $data): CompensationBand
    {
        $this->assertManage($user);

        return CompensationBand::create([
            'company_id' => $user->company_id,
            'name' => $data['name'],
            'grade' => $data['grade'] ?? null,
            'min_salary' => $data['min_salary'],
            'mid_salary' => $data['mid_salary'] ?? null,
            'max_salary' => $data['max_salary'],
            'currency' => $data['currency'] ?? 'INR',
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function updateBand(User $user, CompensationBand $band, array $data): CompensationBand
    {
        $this->resolveBand($user, $band);

        $band->update([
            'name' => $data['name'],
            'grade' => $data['grade'] ?? null,
            'min_salary' => $data['min_salary'],
            'mid_salary' => $data['mid_salary'] ?? null,
            'max_salary' => $data['max_salary'],
            'currency' => $data['currency'] ?? $band->currency,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $band->is_active,
        ]);

        return $band->fresh();
    }

    public function listRecommendations(User $user, array $filters = []): LengthAwarePaginator
    {
        $this->assertManage($user);

        $query = CompensationRecommendation::query()
            ->with(['employee', 'band', 'reviewCycle'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeRecommendation(User $user, array $data): CompensationRecommendation
    {
        $this->assertManage($user);

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($data['employee_id']);

        $currentSalary = $data['current_salary'] ?? $this->resolveCurrentSalary($employee);
        $increasePercent = (float) ($data['recommended_increase_percent'] ?? 0);
        $increaseAmount = $data['recommended_increase_amount'] ?? ($currentSalary ? round($currentSalary * ($increasePercent / 100), 2) : null);
        $newSalary = $data['recommended_new_salary'] ?? ($currentSalary && $increaseAmount ? round($currentSalary + $increaseAmount, 2) : null);

        return CompensationRecommendation::create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'review_cycle_id' => $data['review_cycle_id'] ?? null,
            'band_id' => $data['band_id'] ?? null,
            'current_salary' => $currentSalary,
            'recommended_increase_percent' => $increasePercent,
            'recommended_increase_amount' => $increaseAmount,
            'recommended_new_salary' => $newSalary,
            'merit_rating' => $data['merit_rating'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => CompensationRecommendation::STATUS_DRAFT,
            'created_by_user_id' => $user->id,
        ])->fresh(['employee', 'band', 'reviewCycle']);
    }

    public function updateRecommendation(User $user, CompensationRecommendation $recommendation, array $data): CompensationRecommendation
    {
        $this->resolveRecommendation($user, $recommendation);

        $currentSalary = $data['current_salary'] ?? $recommendation->current_salary;
        $increasePercent = (float) ($data['recommended_increase_percent'] ?? $recommendation->recommended_increase_percent ?? 0);
        $increaseAmount = $data['recommended_increase_amount'] ?? ($currentSalary ? round($currentSalary * ($increasePercent / 100), 2) : null);
        $newSalary = $data['recommended_new_salary'] ?? ($currentSalary && $increaseAmount ? round($currentSalary + $increaseAmount, 2) : null);

        $recommendation->update([
            'review_cycle_id' => $data['review_cycle_id'] ?? $recommendation->review_cycle_id,
            'band_id' => $data['band_id'] ?? $recommendation->band_id,
            'current_salary' => $currentSalary,
            'recommended_increase_percent' => $increasePercent,
            'recommended_increase_amount' => $increaseAmount,
            'recommended_new_salary' => $newSalary,
            'merit_rating' => $data['merit_rating'] ?? $recommendation->merit_rating,
            'notes' => $data['notes'] ?? $recommendation->notes,
        ]);

        return $recommendation->fresh(['employee', 'band', 'reviewCycle']);
    }

    public function updateRecommendationStatus(User $user, CompensationRecommendation $recommendation, string $status): CompensationRecommendation
    {
        $this->resolveRecommendation($user, $recommendation);
        $recommendation->update(['status' => $status]);

        return $recommendation->fresh(['employee', 'band', 'reviewCycle']);
    }

    public function resolveBand(User $user, CompensationBand $band): CompensationBand
    {
        if ((int) $band->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Compensation band not found.');
        }

        $this->assertManage($user);

        return $band;
    }

    public function resolveRecommendation(User $user, CompensationRecommendation $recommendation): CompensationRecommendation
    {
        if ((int) $recommendation->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Compensation recommendation not found.');
        }

        $this->assertManage($user);

        return $recommendation->load(['employee', 'band', 'reviewCycle']);
    }

    private function resolveCurrentSalary(Employee $employee): ?float
    {
        $salary = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('salary_effective_from')
            ->first();

        return $salary?->annual_ctc ? (float) $salary->annual_ctc : null;
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You are not allowed to manage compensation plans.');
        }
    }
}
