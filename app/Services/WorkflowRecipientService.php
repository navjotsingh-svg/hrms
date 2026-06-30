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
    public function hrAndAdminRecipientsForCompany(int $companyId, ?User $exclude = null): Collection
    {
        $recipients = collect();

        $recipients = $recipients->merge($this->usersByRole($companyId, Role::SLUG_COMPANY_ADMIN));
        $recipients = $recipients->merge($this->usersByRole($companyId, Role::SLUG_HR_MANAGER));

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
        $employee->loadMissing(['departments', 'manager.user']);

        $recipients = collect();

        $recipients = $recipients->merge($this->usersByRole($employee->company_id, Role::SLUG_COMPANY_ADMIN));
        $recipients = $recipients->merge($this->usersByRole($employee->company_id, Role::SLUG_HR_MANAGER));
        $recipients = $recipients->merge($this->departmentHeadsForEmployee($employee));

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
    private function usersByRole(int $companyId, string $roleSlug): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->get();
    }

    /** @return Collection<int, User> */
    private function departmentHeadsForEmployee(Employee $employee): Collection
    {
        $departmentIds = $this->departmentIdsForEmployee($employee);

        return User::query()
            ->where('company_id', $employee->company_id)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::SLUG_DEPARTMENT_HEAD))
            ->with('employee.departments')
            ->get()
            ->filter(function (User $user) use ($employee, $departmentIds) {
                if (! $user->employee) {
                    return false;
                }

                if ($departmentIds !== []) {
                    $headDepartmentIds = $this->departmentIdsForEmployee($user->employee);

                    if (array_intersect($headDepartmentIds, $departmentIds) !== []) {
                        return true;
                    }
                }

                $subordinateIds = $this->employeeAccessService->subordinateIdsForUser($user);

                return in_array((int) $employee->id, $subordinateIds, true);
            })
            ->values();
    }

    /** @return array<int, int> */
    private function departmentIdsForEmployee(Employee $employee): array
    {
        $ids = array_filter([(int) $employee->department_id]);

        foreach ($employee->departments ?? [] as $department) {
            $ids[] = (int) $department->id;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
