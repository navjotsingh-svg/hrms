<?php

namespace App\Services;

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
            ->with(['employee', 'keyResults'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($user, $query, $filters);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function store(User $user, array $data): Goal
    {
        $this->assertParticipate($user);
        $employee = $this->resolveTargetEmployee($user, (int) ($data['employee_id'] ?? 0));

        return DB::transaction(function () use ($user, $employee, $data) {
            $goal = Goal::create([
                'company_id' => $user->company_id,
                'employee_id' => $employee->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'status' => $data['status'] ?? Goal::STATUS_ACTIVE,
                'visibility' => $data['visibility'] ?? Goal::VISIBILITY_TEAM,
                'created_by_user_id' => $user->id,
            ]);

            $this->syncKeyResults($goal, $data['key_results'] ?? []);

            return $goal->fresh(['employee', 'keyResults']);
        });
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

            return $goal->fresh(['employee', 'keyResults']);
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

        return $goal->load(['employee', 'keyResults']);
    }

    public function canViewGoal(User $user, Goal $goal): bool
    {
        if ($user->canManagePerformance()) {
            return true;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($employee && (int) $goal->employee_id === (int) $employee->id) {
            return true;
        }

        return match ($goal->visibility) {
            Goal::VISIBILITY_COMPANY => $user->canParticipateInPerformance(),
            Goal::VISIBILITY_TEAM => $employee && $this->employeeAccessService->subordinateIdsForUser($user) !== []
                ? in_array($goal->employee_id, $this->employeeAccessService->subordinateIdsForUser($user), true)
                || (int) $goal->employee_id === (int) $employee->id,
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
            $builder->where('employee_id', $employee->id)
                ->orWhere(function ($team) use ($user, $employee) {
                    $team->where('visibility', Goal::VISIBILITY_COMPANY)
                        ->where('company_id', $user->company_id);
                })
                ->orWhere(function ($team) use ($user, $employee) {
                    $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);
                    if ($subordinateIds !== []) {
                        $team->where('visibility', Goal::VISIBILITY_TEAM)
                            ->whereIn('employee_id', $subordinateIds);
                    }
                });
        });
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

        if ($employee && (int) $goal->employee_id === (int) $employee->id) {
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
