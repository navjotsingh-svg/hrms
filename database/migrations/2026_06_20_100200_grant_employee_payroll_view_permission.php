<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'payroll.view'],
            [
                'name' => 'View Payroll',
                'module' => 'payroll',
                'description' => 'View payroll and payslips',
            ]
        );

        $permissionIds = Permission::query()
            ->whereIn('slug', ['payroll.manage', 'payroll.view'])
            ->pluck('id');

        Role::query()
            ->whereIn('slug', [Role::SLUG_COMPANY_ADMIN, Role::SLUG_HR_MANAGER])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));

        $employeeRole = Role::query()->where('slug', Role::SLUG_EMPLOYEE)->first();

        if ($employeeRole) {
            $employeeRole->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        $permissionId = Permission::query()->where('slug', 'payroll.view')->value('id');

        if (! $permissionId) {
            return;
        }

        $employeeRole = Role::query()->where('slug', Role::SLUG_EMPLOYEE)->first();

        if ($employeeRole) {
            \DB::table('role_permission')
                ->where('role_id', $employeeRole->id)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
