<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['slug' => 'projects.manage', 'name' => 'Manage Projects', 'module' => 'projects', 'description' => 'Create and update projects and assign team members'],
            ['slug' => 'projects.view', 'name' => 'View Projects', 'module' => 'projects', 'description' => 'View assigned projects'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $permissionIds = Permission::query()
            ->whereIn('slug', ['projects.manage', 'projects.view'])
            ->pluck('id', 'slug');

        $manageRoleSlugs = [
            Role::SLUG_COMPANY_ADMIN,
            Role::SLUG_DEPARTMENT_HEAD,
            Role::SLUG_TEAM_LEAD,
        ];

        foreach ($manageRoleSlugs as $slug) {
            $role = Role::query()->where('slug', $slug)->first();

            if (! $role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching([
                $permissionIds['projects.manage'],
                $permissionIds['projects.view'],
            ]);
        }

        $employeeRole = Role::query()->where('slug', Role::SLUG_EMPLOYEE)->first();

        if ($employeeRole) {
            $employeeRole->permissions()->syncWithoutDetaching([
                $permissionIds['projects.view'],
            ]);
        }

        $hrRole = Role::query()->where('slug', Role::SLUG_HR_MANAGER)->first();

        if ($hrRole) {
            $hrRole->permissions()->detach($permissionIds->values());
        }
    }

    public function down(): void
    {
        Permission::query()->whereIn('slug', ['projects.manage', 'projects.view'])->delete();
    }
};
