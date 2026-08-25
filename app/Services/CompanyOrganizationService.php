<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CompanyOrganizationService
{
    public function organizationUnavailableMessage(): string
    {
        return 'There is an issue with your organization. Portal access is temporarily unavailable. Please contact your system administrator or support team.';
    }

    public function employeeIsCompanyAdministrator(Employee $employee): bool
    {
        $employee->loadMissing('role');

        return $employee->role?->slug === Role::SLUG_COMPANY_ADMIN;
    }

    public function hasActiveAdministrator(int $companyId): bool
    {
        return $this->countActiveAdministrators($companyId) > 0;
    }

    public function countActiveAdministrators(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::SLUG_COMPANY_ADMIN))
            ->whereHas('employee', fn ($query) => $query
                ->where('status', 'active')
                ->whereColumn('employees.user_id', 'users.id'))
            ->count();
    }

    public function assertOrganizationOperational(?int $companyId): void
    {
        if ($companyId === null || $this->hasActiveAdministrator($companyId)) {
            return;
        }

        throw new AccessDeniedHttpException($this->organizationUnavailableMessage());
    }

    public function assertActorMayManageAdministratorAccess(User $actor, Employee $employee): void
    {
        if (! $this->employeeIsCompanyAdministrator($employee)) {
            return;
        }

        if ($actor->isSuperAdmin() || $actor->isCompanyAdmin()) {
            return;
        }

        throw new AccessDeniedHttpException(
            'Company administrators cannot be deactivated or have portal access changed by HR. Contact a company administrator.',
        );
    }

    /** @return array<int, string> */
    public function protectedOffboardingRoleSlugs(): array
    {
        return [Role::SLUG_COMPANY_ADMIN];
    }

    public function employeeIsProtectedFromHrOffboarding(Employee $employee): bool
    {
        return $this->employeeIsCompanyAdministrator($employee);
    }

    public function assertActorMayOffboardEmployee(User $actor, Employee $employee): void
    {
        if ($actor->isSuperAdmin() || $actor->isCompanyAdmin()) {
            return;
        }

        $this->assertActorMayManageAdministratorAccess($actor, $employee);
    }
}
