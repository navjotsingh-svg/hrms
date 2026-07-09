<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GoalService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Goal::query()
            ->with(['employee', 'department', 'parent', 'keyResults'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($user, $query, $filters);

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('department', function ($departmentQuery) use ($search) {
                        $departmentQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function store(User $user, array $data): Goal
    {
        $this->assertParticipate($user);

        $level = $data['level'] ?? Goal::LEVEL_INDIVIDUAL;

        if ($level === Goal::LEVEL_COMPANY && ! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('Only performance managers can create company goals.');
        }

        if ($level === Goal::LEVEL_DEPARTMENT && ! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('Only performance managers can create department goals.');
        }

        $employee = null;
        $department = null;

        if ($level === Goal::LEVEL_INDIVIDUAL) {
            $employee = $this->resolveTargetEmployee($user, (int) ($data['employee_id'] ?? 0));
        } elseif ($level === Goal::LEVEL_DEPARTMENT) {
            $department = $this->resolveTargetDepartment($user, (int) ($data['department_id'] ?? 0));
        }

        return DB::transaction(function () use ($user, $employee, $department, $data, $level) {
            $goal = Goal::create([
                'company_id' => $user->company_id,
                'level' => $level,
                'employee_id' => $employee?->id,
                'department_id' => $department?->id,
                'parent_goal_id' => $data['parent_goal_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'status' => $data['status'] ?? Goal::STATUS_ACTIVE,
                'visibility' => $data['visibility'] ?? ($level === Goal::LEVEL_COMPANY ? Goal::VISIBILITY_COMPANY : Goal::VISIBILITY_TEAM),
                'created_by_user_id' => $user->id,
            ]);

            $this->syncKeyResults($goal, $data['key_results'] ?? []);

            return $goal->fresh(['employee', 'department', 'parent', 'keyResults']);
        });
    }

    /** @return array{created: int, skipped: int, goals: \Illuminate\Support\Collection<int, Goal>} */
    public function cascade(User $user, Goal $goal): array
    {
        $goal = $this->resolveGoal($user, $goal);

        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You are not allowed to cascade goals.');
        }

        return match ($goal->level) {
            Goal::LEVEL_COMPANY => $this->cascadeCompanyToDepartments($user, $goal),
            Goal::LEVEL_DEPARTMENT => $this->cascadeDepartmentToEmployees($user, $goal),
            default => throw ValidationException::withMessages([
                'level' => ['Only company or department goals can be cascaded.'],
            ]),
        };
    }

    public function update(User $user, Goal $goal, array $data): Goal
    {
        $this->resolveGoal($user, $goal);
        $this->assertCanEditGoal($user, $goal);

        return DB::transaction(function () use ($goal, $data) {
            $goal->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'status' => $data['status'] ?? $goal->status,
                'visibility' => $data['visibility'] ?? $goal->visibility,
            ]);

            if (isset($data['key_results'])) {
                $this->syncKeyResults($goal, $data['key_results']);
            }

            return $goal->fresh(['employee', 'department', 'parent', 'keyResults']);
        });
    }

    public function updateKeyResult(User $user, GoalKeyResult $keyResult, array $data): GoalKeyResult
    {
        $goal = $keyResult->goal;
        $this->resolveGoal($user, $goal);
        $this->assertCanEditGoal($user, $goal);

        $keyResult->update([
            'title' => $data['title'] ?? $keyResult->title,
            'description' => $data['description'] ?? $keyResult->description,
            'target_value' => $data['target_value'] ?? $keyResult->target_value,
            'current_value' => $data['current_value'] ?? $keyResult->current_value,
            'unit' => $data['unit'] ?? $keyResult->unit,
            'weight' => $data['weight'] ?? $keyResult->weight,
            'status' => $data['status'] ?? $keyResult->status,
            'due_date' => $data['due_date'] ?? $keyResult->due_date,
        ]);

        $goal->recalculateProgress();

        return $keyResult->fresh();
    }

    public function deleteKeyResult(User $user, GoalKeyResult $keyResult): void
    {
        $goal = $keyResult->goal;
        $this->resolveGoal($user, $goal);
        $this->assertCanEditGoal($user, $goal);
        $keyResult->delete();
        $goal->recalculateProgress();
    }

    public function resolveGoal(User $user, Goal $goal): Goal
    {
        if ((int) $goal->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('Goal not found.');
        }

        if (! $this->canViewGoal($user, $goal)) {
            throw new AccessDeniedHttpException('You are not allowed to view this goal.');
        }

        return $goal->load(['employee', 'department', 'parent', 'keyResults']);
    }

    public function canViewGoal(User $user, Goal $goal): bool
    {
        if ($user->canManagePerformance()) {
            return true;
        }

        if ($goal->level === Goal::LEVEL_COMPANY && $goal->visibility === Goal::VISIBILITY_COMPANY) {
            return $user->canParticipateInPerformance();
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($employee && $goal->employee_id && (int) $goal->employee_id === (int) $employee->id) {
            return true;
        }

        if ($employee && $goal->level === Goal::LEVEL_DEPARTMENT && (int) $goal->department_id === (int) $employee->department_id) {
            return true;
        }

        return match ($goal->visibility) {
            Goal::VISIBILITY_COMPANY => $user->canParticipateInPerformance(),
            Goal::VISIBILITY_TEAM => $employee && $this->employeeAccessService->subordinateIdsForUser($user) !== []
                ? (
                    in_array($goal->employee_id, $this->employeeAccessService->subordinateIdsForUser($user), true)
                    || ($goal->employee_id && (int) $goal->employee_id === (int) $employee->id)
                )
                : false,
            default => false,
        };
    }

    private function applyVisibilityScope(User $user, $query, array $filters): void
    {
        if ($user->canManagePerformance() && ($filters['scope'] ?? '') === 'all') {
            if (! empty($filters['employee_id'])) {
                $query->where('employee_id', (int) $filters['employee_id']);
            }

            return;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if (! $employee) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->canManagePerformance() && ($filters['scope'] ?? '') === 'team') {
            $ids = array_values(array_unique([
                $employee->id,
                ...$this->employeeAccessService->subordinateIdsForUser($user),
            ]));
            $query->whereIn('employee_id', $ids);

            return;
        }

        $query->where(function ($builder) use ($user, $employee) {
            $builder->where('level', Goal::LEVEL_COMPANY)
                ->where('visibility', Goal::VISIBILITY_COMPANY)
                ->orWhere(function ($departmentGoals) use ($employee) {
                    if (! $employee?->department_id) {
                        return;
                    }

                    $departmentGoals->where('level', Goal::LEVEL_DEPARTMENT)
                        ->where('department_id', $employee->department_id);
                })
                ->orWhere('employee_id', $employee->id)
                ->orWhere(function ($team) use ($user, $employee) {
                    $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
                    if ($subordinateIds !== []) {
                        $team->where('visibility', Goal::VISIBILITY_TEAM)
                            ->whereIn('employee_id', $subordinateIds);
                    }
                });
        });
    }

    /** @return array{created: int, skipped: int, goals: \Illuminate\Support\Collection<int, Goal>} */
    private function cascadeCompanyToDepartments(User $user, Goal $goal): array
    {
        $departments = Department::query()
            ->where('company_id', $goal->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $created = collect();
        $skipped = 0;

        foreach ($departments as $department) {
            $exists = Goal::query()
                ->where('parent_goal_id', $goal->id)
                ->where('department_id', $department->id)
                ->where('level', Goal::LEVEL_DEPARTMENT)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $child = Goal::create([
                'company_id' => $goal->company_id,
                'level' => Goal::LEVEL_DEPARTMENT,
                'department_id' => $department->id,
                'parent_goal_id' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'period_start' => $goal->period_start,
                'period_end' => $goal->period_end,
                'status' => Goal::STATUS_ACTIVE,
                'visibility' => Goal::VISIBILITY_TEAM,
                'created_by_user_id' => $user->id,
            ]);

            $this->duplicateKeyResults($goal, $child);
            $created->push($child->fresh(['department', 'keyResults']));
        }

        return [
            'created' => $created->count(),
            'skipped' => $skipped,
            'goals' => $created,
        ];
    }

    /** @return array{created: int, skipped: int, goals: \Illuminate\Support\Collection<int, Goal>} */
    private function cascadeDepartmentToEmployees(User $user, Goal $goal): array
    {
        if (! $goal->department_id) {
            throw ValidationException::withMessages([
                'department_id' => ['Department goal must be linked to a department before cascading.'],
            ]);
        }

        $employees = Employee::query()
            ->where('company_id', $goal->company_id)
            ->where('department_id', $goal->department_id)
            ->where('status', 'active')
            ->orderedByName()
            ->get();

        $created = collect();
        $skipped = 0;

        foreach ($employees as $employee) {
            $exists = Goal::query()
                ->where('parent_goal_id', $goal->id)
                ->where('employee_id', $employee->id)
                ->where('level', Goal::LEVEL_INDIVIDUAL)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $child = Goal::create([
                'company_id' => $goal->company_id,
                'level' => Goal::LEVEL_INDIVIDUAL,
                'employee_id' => $employee->id,
                'department_id' => $goal->department_id,
                'parent_goal_id' => $goal->id,
                'title' => $goal->title,
                'description' => $goal->description,
                'period_start' => $goal->period_start,
                'period_end' => $goal->period_end,
                'status' => Goal::STATUS_ACTIVE,
                'visibility' => Goal::VISIBILITY_TEAM,
                'created_by_user_id' => $user->id,
            ]);

            $this->duplicateKeyResults($goal, $child);
            $created->push($child->fresh(['employee', 'keyResults']));
        }

        return [
            'created' => $created->count(),
            'skipped' => $skipped,
            'goals' => $created,
        ];
    }

    private function duplicateKeyResults(Goal $source, Goal $target): void
    {
        $source->loadMissing('keyResults');

        foreach ($source->keyResults as $index => $keyResult) {
            GoalKeyResult::create([
                'goal_id' => $target->id,
                'title' => $keyResult->title,
                'description' => $keyResult->description,
                'target_value' => $keyResult->target_value,
                'current_value' => 0,
                'unit' => $keyResult->unit,
                'weight' => $keyResult->weight,
                'status' => GoalKeyResult::STATUS_NOT_STARTED,
                'due_date' => $keyResult->due_date,
                'sort_order' => $index + 1,
            ]);
        }

        $target->recalculateProgress();
    }

    private function resolveTargetDepartment(User $user, int $departmentId): Department
    {
        if ($departmentId <= 0) {
            throw ValidationException::withMessages([
                'department_id' => ['Select a department for this goal.'],
            ]);
        }

        return Department::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($departmentId);
    }

    private function syncKeyResults(Goal $goal, array $keyResults): void
    {
        $existingIds = [];

        foreach (array_values($keyResults) as $index => $kr) {
            if (empty($kr['title'])) {
                continue;
            }

            if (! empty($kr['id'])) {
                $model = GoalKeyResult::query()
                    ->where('goal_id', $goal->id)
                    ->where('id', $kr['id'])
                    ->first();

                if ($model) {
                    $model->update([
                        'title' => $kr['title'],
                        'description' => $kr['description'] ?? null,
                        'target_value' => $kr['target_value'] ?? 100,
                        'current_value' => $kr['current_value'] ?? 0,
                        'unit' => $kr['unit'] ?? null,
                        'weight' => $kr['weight'] ?? 1,
                        'status' => $kr['status'] ?? GoalKeyResult::STATUS_NOT_STARTED,
                        'due_date' => $kr['due_date'] ?? null,
                        'sort_order' => $index + 1,
                    ]);
                    $existingIds[] = $model->id;

                    continue;
                }
            }

            $created = GoalKeyResult::create([
                'goal_id' => $goal->id,
                'title' => $kr['title'],
                'description' => $kr['description'] ?? null,
                'target_value' => $kr['target_value'] ?? 100,
                'current_value' => $kr['current_value'] ?? 0,
                'unit' => $kr['unit'] ?? null,
                'weight' => $kr['weight'] ?? 1,
                'status' => $kr['status'] ?? GoalKeyResult::STATUS_NOT_STARTED,
                'due_date' => $kr['due_date'] ?? null,
                'sort_order' => $index + 1,
            ]);
            $existingIds[] = $created->id;
        }

        GoalKeyResult::query()
            ->where('goal_id', $goal->id)
            ->whereNotIn('id', $existingIds)
            ->delete();

        $goal->recalculateProgress();
    }

    private function resolveTargetEmployee(User $user, int $employeeId): Employee
    {
        $own = $this->employeeAccessService->linkedEmployee($user);

        if (! $own) {
            throw new AccessDeniedHttpException('No employee profile linked to your account.');
        }

        if ($employeeId === 0 || $employeeId === (int) $own->id) {
            return $own;
        }

        if ($user->canManagePerformance()) {
            return Employee::query()
                ->where('company_id', $user->company_id)
                ->findOrFail($employeeId);
        }

        throw new AccessDeniedHttpException('You can only create goals for yourself.');
    }

    private function assertCanEditGoal(User $user, Goal $goal): void
    {
        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($user->canManagePerformance()) {
            return;
        }

        if ($employee && $goal->employee_id && (int) $goal->employee_id === (int) $employee->id) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to edit this goal.');
    }

    private function assertParticipate(User $user): void
    {
        if (! $user->canParticipateInPerformance()) {
            throw new AccessDeniedHttpException('You are not allowed to manage goals.');
        }
    }
}
