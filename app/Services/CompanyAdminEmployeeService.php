<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;

class CompanyAdminEmployeeService
{
    public function __construct(private EmployeeAccessService $employeeAccessService) {}

    public function ensureForAdmin(User $user): ?Employee
    {
        $user->loadMissing(['role', 'company']);

        if (! $user->company_id || ! $user->isCompanyAdmin()) {
            return null;
        }

        $linked = $this->employeeAccessService->linkedEmployee($user);

        if ($linked) {
            return $this->syncAdminEmployeeFlags($linked, $user);
        }

        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->first();

        if ($employee) {
            return $this->linkAndSync($employee, $user);
        }

        return $this->createForAdmin($user);
    }

    public function ensureForCompany(Company $company): ?Employee
    {
        $company->loadMissing('adminUser');

        if (! $company->adminUser) {
            return null;
        }

        return $this->ensureForAdmin($company->adminUser);
    }

    private function createForAdmin(User $user): Employee
    {
        [$firstName, $lastName] = $this->splitName($user->name ?: $user->company?->contact_person_name ?: 'Administrator');
        $adminRoleId = Role::idFor(Role::SLUG_COMPANY_ADMIN) ?? $user->role_id;
        $today = now()->toDateString();

        return Employee::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'role_id' => $adminRoleId,
            'employee_code' => $this->generateEmployeeCode((int) $user->company_id),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
            'designation' => 'Company Administrator',
            'status' => 'active',
            'is_paid_employee' => true,
            'employment_type' => 'full_time',
            'joining_date' => $today,
            'portal_access_date' => $today,
        ]);
    }

    private function linkAndSync(Employee $employee, User $user): Employee
    {
        $adminRoleId = Role::idFor(Role::SLUG_COMPANY_ADMIN) ?? $user->role_id;

        if ((int) $employee->user_id !== (int) $user->id) {
            $employee->user_id = $user->id;
        }

        if ((int) $employee->role_id !== (int) $adminRoleId) {
            $employee->role_id = $adminRoleId;
        }

        if ($employee->isDirty()) {
            $employee->save();
        }

        return $this->syncAdminEmployeeFlags($employee->fresh(), $user);
    }

    private function syncAdminEmployeeFlags(Employee $employee, User $user): Employee
    {
        $adminRoleId = Role::idFor(Role::SLUG_COMPANY_ADMIN) ?? $user->role_id;
        $updates = [];

        if ((int) $employee->user_id !== (int) $user->id) {
            $updates['user_id'] = $user->id;
        }

        if ((int) $employee->role_id !== (int) $adminRoleId) {
            $updates['role_id'] = $adminRoleId;
        }

        if (! $employee->portal_access_date && $employee->user_id) {
            $updates['portal_access_date'] = $user->created_at?->toDateString()
                ?? now()->toDateString();
        }

        if (! $employee->is_paid_employee) {
            $updates['is_paid_employee'] = true;
        }

        if ($employee->status !== 'active') {
            $updates['status'] = 'active';
        }

        if (! $employee->joining_date) {
            $updates['joining_date'] = now()->toDateString();
        }

        if ($employee->email !== $user->email) {
            $updates['email'] = $user->email;
        }

        if ($updates !== []) {
            $employee->update($updates);
        }

        return $employee->fresh();
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        $firstName = $parts[0] ?? 'Administrator';
        $lastName = $parts[1] ?? '';

        return [$firstName, $lastName];
    }

    private function generateEmployeeCode(int $companyId): string
    {
        $latestCode = Employee::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('employee_code');

        $nextNumber = 1;

        if ($latestCode && preg_match('/(\d+)$/', $latestCode, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return 'EMP'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
