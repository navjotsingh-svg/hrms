<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

class EmployeeAccessService
{
    public function canManage(User $user): bool
    {
        return $user->hasPermission('employees.manage');
    }

    public function canViewAny(User $user): bool
    {
        return $this->canManage($user) || $user->hasPermission('employees.view');
    }

    public function canViewAll(User $user): bool
    {
        return $this->canManage($user);
    }

    public function linkedEmployee(User $user): ?Employee
    {
        $user->loadMissing('employee');

        if ($user->employee) {
            return $user->employee;
        }

        if (! $user->company_id) {
            return null;
        }

        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Active employees in the reporting tree below the signed-in user.
     *
     * @return array<int>
     */
    public function subordinateIdsForUser(User $user): array
    {
        $employee = $this->linkedEmployee($user);

        if (! $employee) {
            return [];
        }

        $subordinateIds = $this->descendantIds((int) $employee->id, (int) $user->company_id);

        if ($subordinateIds === []) {
            return [];
        }

        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereIn('id', $subordinateIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int>|null Null means no restriction (view all).
     */
    public function visibleEmployeeIds(User $user): ?array
    {
        if ($this->canViewAll($user)) {
            return null;
        }

        if (! $user->hasPermission('employees.view')) {
            return [];
        }

        return $this->subordinateIdsForUser($user);
    }

    public function canView(User $user, Employee $employee): bool
    {
        if ((int) $employee->company_id !== (int) $user->company_id) {
            return false;
        }

        $visibleIds = $this->visibleEmployeeIds($user);

        if ($visibleIds === null) {
            return true;
        }

        return in_array($employee->id, $visibleIds, true);
    }

    public function assertCanView(User $user, Employee $employee): void
    {
        if (! $this->canView($user, $employee)) {
            abort(404);
        }
    }

    public function assertCanManage(User $user): void
    {
        if (! $this->canManage($user)) {
            abort(403, 'You do not have permission to manage employees.');
        }
    }

    /**
     * @return array<int>
     */
    public function descendantIds(int $managerEmployeeId, int $companyId): array
    {
        $childrenByManager = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('manager_id')
            ->get(['id', 'manager_id'])
            ->groupBy(fn (Employee $employee) => (int) $employee->manager_id);

        $descendants = [];
        $queue = [(int) $managerEmployeeId];

        while ($queue !== []) {
            $currentManagerId = array_shift($queue);

            foreach ($childrenByManager->get($currentManagerId, collect()) as $report) {
                $employeeId = (int) $report->id;

                if (in_array($employeeId, $descendants, true)) {
                    continue;
                }

                $descendants[] = $employeeId;
                $queue[] = $employeeId;
            }
        }

        return $descendants;
    }

    public function wouldCreateCycle(int $employeeId, ?int $managerId, int $companyId): bool
    {
        if (! $managerId || $employeeId === $managerId) {
            return $employeeId === $managerId;
        }

        $descendants = $this->descendantIds($employeeId, $companyId);

        return in_array($managerId, $descendants, true);
    }
}
