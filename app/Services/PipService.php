<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PipKeyResult;
use App\Models\PipPlan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PipService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = PipPlan::query()
            ->with(['employee', 'manager', 'keyResults'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('start_date');

        if (! $user->canManagePips()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($builder) use ($employee, $user) {
                    $builder->where('employee_id', $employee->id)
                        ->orWhere('manager_employee_id', $employee->id);

                    if ($user->canReviewPerformance()) {
                        $subordinates = $this->employeeAccessService->subordinateIdsForUser($user);
                        if ($subordinates !== []) {
                            $builder->orWhereIn('employee_id', $subordinates);
                        }
                    }
                });
            }
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function store(User $user, array $data): PipPlan
    {
        $this->assertManage($user);

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($data['employee_id']);

        return DB::transaction(function () use ($user, $employee, $data) {
            $pip = PipPlan::create([
                'company_id' => $user->company_id,
                'employee_id' => $employee->id,
                'manager_employee_id' => $data['manager_employee_id'] ?? $employee->manager_id,
                'title' => $data['title'],
                'reason' => $data['reason'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => PipPlan::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            $this->syncKeyResults($pip, $data['key_results'] ?? []);

            return $pip->fresh(['employee', 'manager', 'keyResults']);
        });
    }

    public function update(User $user, PipPlan $pip, array $data): PipPlan
    {
        $this->resolvePip($user, $pip);
        $this->assertManage($user);

        return DB::transaction(function () use ($pip, $data) {
            $pip->update([
                'title' => $data['title'],
                'reason' => $data['reason'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'manager_employee_id' => $data['manager_employee_id'] ?? $pip->manager_employee_id,
                'outcome_notes' => $data['outcome_notes'] ?? $pip->outcome_notes,
            ]);

            if (isset($data['key_results'])) {
                $this->syncKeyResults($pip, $data['key_results']);
            }

            return $pip->fresh(['employee', 'manager', 'keyResults']);
        });
    }

    public function updateStatus(User $user, PipPlan $pip, string $status): PipPlan
    {
        $this->resolvePip($user, $pip);
        $this->assertManage($user);

        if (! in_array($status, [
            PipPlan::STATUS_DRAFT,
            PipPlan::STATUS_ACTIVE,
            PipPlan::STATUS_COMPLETED,
            PipPlan::STATUS_FAILED,
            PipPlan::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid PIP status.']);
        }

        $pip->update(['status' => $status]);

        return $pip->fresh(['employee', 'manager', 'keyResults']);
    }

    public function updateKeyResult(User $user, PipKeyResult $keyResult, array $data): PipKeyResult
    {
        $pip = $keyResult->pipPlan;
        $this->resolvePip($user, $pip);
        $this->assertCanTrack($user, $pip);

        $keyResult->update([
            'title' => $data['title'] ?? $keyResult->title,
            'description' => $data['description'] ?? $keyResult->description,
            'target_date' => $data['target_date'] ?? $keyResult->target_date,
            'status' => $data['status'] ?? $keyResult->status,
        ]);

        return $keyResult->fresh();
    }

    public function resolvePip(User $user, PipPlan $pip): PipPlan
    {
        if ((int) $pip->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('PIP not found.');
        }

        return $pip->load(['employee', 'manager', 'keyResults']);
    }

    private function syncKeyResults(PipPlan $pip, array $keyResults): void
    {
        $keepIds = [];

        foreach (array_values($keyResults) as $index => $kr) {
            if (empty($kr['title'])) {
                continue;
            }

            if (! empty($kr['id'])) {
                $model = PipKeyResult::query()->where('pip_plan_id', $pip->id)->where('id', $kr['id'])->first();
                if ($model) {
                    $model->update([
                        'title' => $kr['title'],
                        'description' => $kr['description'] ?? null,
                        'target_date' => $kr['target_date'] ?? null,
                        'status' => $kr['status'] ?? PipKeyResult::STATUS_PENDING,
                        'sort_order' => $index + 1,
                    ]);
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            $created = PipKeyResult::create([
                'pip_plan_id' => $pip->id,
                'title' => $kr['title'],
                'description' => $kr['description'] ?? null,
                'target_date' => $kr['target_date'] ?? null,
                'status' => $kr['status'] ?? PipKeyResult::STATUS_PENDING,
                'sort_order' => $index + 1,
            ]);
            $keepIds[] = $created->id;
        }

        PipKeyResult::query()->where('pip_plan_id', $pip->id)->whereNotIn('id', $keepIds)->delete();
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePips()) {
            throw new AccessDeniedHttpException('You are not allowed to manage PIPs.');
        }
    }

    private function assertCanTrack(User $user, PipPlan $pip): void
    {
        if ($user->canManagePips()) {
            return;
        }

        $employee = $this->employeeAccessService->linkedEmployee($user);

        if ($employee && (
            (int) $pip->employee_id === (int) $employee->id
            || (int) $pip->manager_employee_id === (int) $employee->id
        )) {
            return;
        }

        throw new AccessDeniedHttpException('You are not allowed to update this PIP.');
    }
}
