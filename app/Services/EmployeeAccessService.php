<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

class EmployeeAccessService
{
    public function canManage(User $user): bool
    {
        return $user->isCompanyAdmin() || $user->hasPermission('employees.manage');
    }

    public function canViewAny(User $user): bool
    {
        return $this->canManage($user) || $user->hasPermission('employees.view');
    }

    public function canViewAll(User $user): bool
    {
        return $this->canManage($user);
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

        $employee = $user->employee;

        if (! $employee) {
            return [];
        }

        return $this->descendantIds($employee->id, (int) $user->company_id);
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
        $reportsByManager = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('manager_id')
            ->pluck('manager_id', 'id');

        $descendants = [];
        $queue = [$managerEmployeeId];

        while ($queue !== []) {
            $currentManagerId = array_shift($queue);

            foreach ($reportsByManager as $employeeId => $managerId) {
                if ((int) $managerId !== $currentManagerId) {
                    continue;
                }

                if (in_array($employeeId, $descendants, true)) {
                    continue;
                }

                $descendants[] = (int) $employeeId;
                $queue[] = (int) $employeeId;
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
