<?php

namespace App\Services;

use App\Models\Competency;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CompetencyService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listCompetencies(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Competency::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name');

        if (($filters['active_only'] ?? false) === true || ($filters['active_only'] ?? '') === '1') {
            $query->where('is_active', true);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeCompetency(User $user, array $data): Competency
    {
        $this->assertManage($user);

        return Competency::create([
            'company_id' => $user->company_id,
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'max_level' => $data['max_level'] ?? 5,
            'is_active' => $data['is_active'] ?? true,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function updateCompetency(User $user, Competency $competency, array $data): Competency
    {
        $this->resolveCompetency($user, $competency);
        $this->assertManage($user);

        $competency->update([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'max_level' => $data['max_level'] ?? $competency->max_level,
            'is_active' => $data['is_active'] ?? $competency->is_active,
        ]);

        return $competency->fresh();
    }

    public function listEmployeeCompetencies(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = EmployeeCompetency::query()
            ->with(['employee', 'competency'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('updated_at');

        if (! $user->canManagePerformance()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                $query->whereRaw('1 = 0');
            } else {
                $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
                $visibleIds = array_values(array_unique([$employee->id, ...$subordinateIds]));
                $query->whereIn('employee_id', $visibleIds);
            }
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->whereHas('employee', function ($employeeQuery) use ($search) {
                    $employeeQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('competency', function ($competencyQuery) use ($search) {
                    $competencyQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function storeEmployeeCompetency(User $user, array $data): EmployeeCompetency
    {
        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($data['employee_id']);

        $this->assertCanManageEmployeeCompetency($user, $employee);

        $competency = Competency::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($data['competency_id']);

        return EmployeeCompetency::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'competency_id' => $competency->id,
            ],
            [
                'company_id' => $user->company_id,
                'current_level' => $data['current_level'] ?? 1,
                'target_level' => $data['target_level'] ?? 3,
                'notes' => $data['notes'] ?? null,
                'assessed_at' => now()->toDateString(),
                'assessed_by_user_id' => $user->id,
            ]
        )->fresh(['employee', 'competency']);
    }

    public function updateEmployeeCompetency(User $user, EmployeeCompetency $record, array $data): EmployeeCompetency
    {
        $this->resolveEmployeeCompetency($user, $record);
        $this->assertCanManageEmployeeCompetency($user, $record->employee);

        $record->update([
            'current_level' => $data['current_level'] ?? $record->current_level,
            'target_level' => $data['target_level'] ?? $record->target_level,
            'notes' => $data['notes'] ?? $record->notes,
            'assessed_at' => now()->toDateString(),
            'assessed_by_user_id' => $user->id,
        ]);

        return $record->fresh(['employee', 'competency']);
    }

    public function resolveCompetency(User $user, Competency $competency): Competency
    {
        if ((int) $competency->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Competency not found.');
        }

        return $competency;
    }

    public function resolveEmployeeCompetency(User $user, EmployeeCompetency $record): EmployeeCompetency
    {
        if ((int) $record->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Employee competency not found.');
        }

        return $record->load(['employee', 'competency']);
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You are not allowed to manage competencies.');
        }
    }

    private function assertCanManageEmployeeCompetency(User $user, Employee $employee): void
    {
        if ($user->canManagePerformance()) {
            return;
        }

        $linked = $this->employeeAccessService->linkedEmployee($user);
        $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

        if ($linked && (int) $linked->id === (int) $employee->id) {
            return;
        }

        if ($subordinateIds !== [] && in_array($employee->id, $subordinateIds, true)) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to update this employee competency profile.');
    }
}
