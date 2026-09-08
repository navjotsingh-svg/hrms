<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['slug' => 'expenses.apply', 'name' => 'Apply Expenses', 'module' => 'expenses', 'description' => 'Submit expense claims and groups'],
        ['slug' => 'expenses.approve', 'name' => 'Approve Expenses', 'module' => 'expenses', 'description' => 'Approve or reject expense claims'],
        ['slug' => 'expenses.manage', 'name' => 'Manage Expenses', 'module' => 'expenses', 'description' => 'Manage expense types and view all company expenses'],
    ];

    private const ROLE_PERMISSIONS = [
        Role::SLUG_COMPANY_ADMIN => ['expenses.apply', 'expenses.approve', 'expenses.manage'],
        Role::SLUG_HR_MANAGER => ['expenses.apply', 'expenses.approve', 'expenses.manage'],
        Role::SLUG_DEPARTMENT_HEAD => ['expenses.apply'],
        Role::SLUG_TEAM_LEAD => ['expenses.apply'],
        Role::SLUG_EMPLOYEE => ['expenses.apply'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    public function down(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $role->permissions()->detach($permissionIds);
        }

        Permission::query()->whereIn('id', $permissionIds)->delete();
    }
};
