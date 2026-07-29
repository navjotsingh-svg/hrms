<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class WorkflowRecipientService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    /** @return Collection<int, User> */
    public function hrRecipientsForCompany(int $companyId, ?User $exclude = null): Collection
    {
        return $this->usersByRole($companyId, Role::SLUG_HR_MANAGER)
            ->filter(fn (User $user) => filled($user->email))
            ->when($exclude, fn (Collection $collection) => $collection->filter(
                fn (User $user) => (int) $user->id !== (int) $exclude->id,
            ))
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, User> */
    public function documentReviewRecipientsForCompany(int $companyId, ?User $exclude = null): Collection
    {
        $recipients = collect();

        $recipients = $recipients->merge($this->usersByRole($companyId, Role::SLUG_HR_MANAGER));
        $recipients = $recipients->merge($this->usersByRole($companyId, Role::SLUG_COMPANY_ADMIN));
        $recipients = $recipients->merge(
            User::query()
                ->where('company_id', $companyId)
                ->whereHas('role.permissions', fn ($query) => $query->where('slug', 'employees.manage'))
                ->get(),
        );

        return $recipients
            ->filter(fn (User $user) => filled($user->email))
            ->when($exclude, fn (Collection $collection) => $collection->filter(
                fn (User $user) => (int) $user->id !== (int) $exclude->id,
            ))
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, User> */
    public function hrAndAdminRecipientsForCompany(int $companyId, ?User $exclude = null): Collection
    {
        return $this->hrRecipientsForCompany($companyId, $exclude);
    }

    /** @return Collection<int, User> */
    public function directManagerRecipientsForEmployee(Employee $employee, ?User $exclude = null): Collection
    {
        $employee->loadMissing('manager.user');

        $recipients = collect();

        if ($employee->manager?->user) {
            $recipients->push($employee->manager->user);
        }

        return $recipients
            ->filter(fn (User $user) => filled($user->email))
            ->when($exclude, fn (Collection $collection) => $collection->filter(
                fn (User $user) => (int) $user->id !== (int) $exclude->id,
            ))
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, User> */
    public function stakeholdersForEmployee(Employee $employee, ?User $exclude = null): Collection
    {
        return $this->directManagerRecipientsForEmployee($employee, $exclude);
    }

    /** @return Collection<int, User> */
    public function managersInChainRecipientsForEmployee(Employee $employee, ?User $exclude = null): Collection
    {
        return $this->directManagerRecipientsForEmployee($employee, $exclude);
    }

    /** @return Collection<int, User> */
    private function usersByRole(int $companyId, string $roleSlug): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->get();
    }
}
