<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PerformanceKpi;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PerformanceKpiService
{
    public function __construct(
        private EmployeeAccessService $employeeAccessService,
        private GoalProgressSyncService $goalProgressSyncService,
    ) {}

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $this->assertParticipate($user);

        $query = PerformanceKpi::query()
            ->with(['employee', 'linkedKeyResults.goal'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('updated_at');

        if (! $user->canManagePerformance()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('employee_id', $employee->id);
            }
        } elseif (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

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

    public function store(User $user, array $data): PerformanceKpi
    {
        $this->assertManage($user);
        $employee = $this->resolveEmployee($user, (int) $data['employee_id']);

        return PerformanceKpi::create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_value' => $data['target_value'] ?? 100,
            'current_value' => $data['current_value'] ?? 0,
            'unit' => $data['unit'] ?? null,
            'frequency' => $data['frequency'] ?? 'quarterly',
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'status' => $data['status'] ?? PerformanceKpi::STATUS_ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function update(User $user, PerformanceKpi $kpi, array $data): PerformanceKpi
    {
        $this->resolve($user, $kpi);

        if ($user->canManagePerformance()) {
            if (isset($data['employee_id'])) {
                $employee = $this->resolveEmployee($user, (int) $data['employee_id']);
                $kpi->employee_id = $employee->id;
            }

            $kpi->update([
                'employee_id' => $kpi->employee_id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'target_value' => $data['target_value'] ?? $kpi->target_value,
                'current_value' => $data['current_value'] ?? $kpi->current_value,
                'unit' => $data['unit'] ?? null,
                'frequency' => $data['frequency'] ?? $kpi->frequency,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'status' => $data['status'] ?? $kpi->status,
            ]);
        } else {
            if (! array_key_exists('current_value', $data)) {
                throw new AccessDeniedHttpException('You can only update the current value on your KPIs.');
            }

            $kpi->update([
                'current_value' => $data['current_value'],
            ]);
        }

        $this->goalProgressSyncService->onKpiUpdated($kpi->fresh(['employee', 'linkedKeyResults.goal']));

        return $kpi->fresh(['employee', 'linkedKeyResults.goal']);
    }

    public function delete(User $user, PerformanceKpi $kpi): void
    {
        $this->resolve($user, $kpi);
        $this->assertManage($user);

        $kpi->delete();
    }

    public function resolve(User $user, PerformanceKpi $kpi): PerformanceKpi
    {
        if ((int) $kpi->company_id !== (int) $user->company_id) {
            throw new NotFoundHttpException('KPI not found.');
        }

        if (! $user->canManagePerformance()) {
            $employee = $this->employeeAccessService->linkedEmployee($user);

            if (! $employee || (int) $kpi->employee_id !== (int) $employee->id) {
                throw new AccessDeniedHttpException('You do not have permission to access this KPI.');
            }
        }

        $kpi->loadMissing(['employee', 'linkedKeyResults.goal']);

        return $kpi;
    }

    private function resolveEmployee(User $user, int $employeeId): Employee
    {
        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('id', $employeeId)
            ->first();

        if (! $employee) {
            throw new NotFoundHttpException('Employee not found.');
        }

        return $employee;
    }

    private function assertManage(User $user): void
    {
        if (! $user->canManagePerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to manage KPIs.');
        }
    }

    private function assertParticipate(User $user): void
    {
        if (! $user->canManagePerformance() && ! $user->canParticipateInPerformance()) {
            throw new AccessDeniedHttpException('You do not have permission to view KPIs.');
        }
    }
}
