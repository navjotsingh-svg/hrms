<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['slug' => 'performance.manage', 'name' => 'Manage Performance', 'module' => 'performance', 'description' => 'Configure review cycles, goals visibility, and PIPs'],
        ['slug' => 'performance.participate', 'name' => 'Participate in Performance', 'module' => 'performance', 'description' => 'Create goals and complete self-reviews'],
        ['slug' => 'performance.review', 'name' => 'Review Performance', 'module' => 'performance', 'description' => 'Complete manager and peer performance reviews'],
        ['slug' => 'pip.manage', 'name' => 'Manage PIPs', 'module' => 'performance', 'description' => 'Create and track performance improvement plans'],
    ];

    private const ROLE_PERMISSIONS = [
        Role::SLUG_COMPANY_ADMIN => ['performance.manage', 'performance.participate', 'performance.review', 'pip.manage'],
        Role::SLUG_HR_MANAGER => ['performance.manage', 'performance.participate', 'performance.review', 'pip.manage'],
        Role::SLUG_DEPARTMENT_HEAD => ['performance.participate', 'performance.review'],
        Role::SLUG_TEAM_LEAD => ['performance.participate', 'performance.review'],
        Role::SLUG_EMPLOYEE => ['performance.participate'],
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

        foreach (Role::query()->where('scope', 'company')->get() as $role) {
            $role->permissions()->detach($permissionIds);
        }

        Permission::query()->whereIn('id', $permissionIds)->delete();
    }
};
