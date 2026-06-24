<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['slug' => 'hiring.manage', 'name' => 'Manage Hiring', 'module' => 'hiring', 'description' => 'Configure hiring settings, templates, and pipelines'],
        ['slug' => 'hiring.requisition.create', 'name' => 'Create Job Requisitions', 'module' => 'hiring', 'description' => 'Submit job requisition requests'],
        ['slug' => 'hiring.requisition.approve', 'name' => 'Approve Job Requisitions', 'module' => 'hiring', 'description' => 'Review and approve or reject job requisitions'],
        ['slug' => 'hiring.interview', 'name' => 'Conduct Interviews', 'module' => 'hiring', 'description' => 'Schedule and manage candidate interviews'],
        ['slug' => 'hiring.careers.publish', 'name' => 'Publish Careers Page', 'module' => 'hiring', 'description' => 'Publish and manage the public careers page'],
    ];

    private const ROLE_PERMISSIONS = [
        Role::SLUG_COMPANY_ADMIN => [
            'hiring.manage',
            'hiring.requisition.create',
            'hiring.requisition.approve',
            'hiring.interview',
            'hiring.careers.publish',
        ],
        Role::SLUG_HR_MANAGER => [
            'hiring.manage',
            'hiring.requisition.create',
            'hiring.requisition.approve',
            'hiring.interview',
            'hiring.careers.publish',
        ],
        Role::SLUG_DEPARTMENT_HEAD => [
            'hiring.requisition.create',
            'hiring.interview',
        ],
        Role::SLUG_TEAM_LEAD => [
            'hiring.requisition.create',
            'hiring.interview',
        ],
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
