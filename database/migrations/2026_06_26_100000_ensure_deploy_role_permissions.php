<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent permission sync for live deploys where code was uploaded
 * but migrations were not run or custom roles were created before new slugs existed.
 */
return new class extends Migration
{
    private const HOME_PERMISSIONS = [
        ['slug' => 'home.view', 'name' => 'View Home', 'module' => 'home', 'description' => 'Access the Home section'],
        ['slug' => 'home.dashboard.view', 'name' => 'View Home Dashboard', 'module' => 'home', 'description' => 'View dashboard widgets and charts on Home'],
        ['slug' => 'home.dashboard.manage', 'name' => 'Manage Home Dashboard', 'module' => 'home', 'description' => 'Customize dashboard widget layout'],
        ['slug' => 'home.moments.view', 'name' => 'View Moments', 'module' => 'home', 'description' => 'View company moments feed'],
        ['slug' => 'home.moments.post', 'name' => 'Post Moments', 'module' => 'home', 'description' => 'Create posts in the moments feed'],
    ];

    private const ROLE_PERMISSIONS = [
        Role::SLUG_EMPLOYEE => [
            'home.view',
            'home.moments.view',
        ],
        Role::SLUG_TEAM_LEAD => [
            'home.view',
            'home.dashboard.view',
            'home.moments.view',
            'home.moments.post',
        ],
        Role::SLUG_DEPARTMENT_HEAD => [
            'home.view',
            'home.dashboard.view',
            'home.moments.view',
            'home.moments.post',
        ],
        Role::SLUG_HR_MANAGER => [
            'home.view',
            'home.dashboard.view',
            'home.dashboard.manage',
            'home.moments.view',
            'home.moments.post',
        ],
        Role::SLUG_COMPANY_ADMIN => [
            'home.view',
            'home.dashboard.view',
            'home.dashboard.manage',
            'home.moments.view',
            'home.moments.post',
        ],
    ];

    public function up(): void
    {
        foreach (self::HOME_PERMISSIONS as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id');

            if ($permissionIds->isNotEmpty()) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
