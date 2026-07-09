<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const ROLE_PERMISSIONS = [
        Role::SLUG_EMPLOYEE => [
            'home.dashboard.view',
            'home.dashboard.manage',
        ],
        Role::SLUG_TEAM_LEAD => [
            'home.dashboard.manage',
        ],
    ];

    public function up(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id');

            if ($permissionIds->isEmpty()) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    public function down(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id');

            if ($permissionIds->isEmpty()) {
                continue;
            }

            $role->permissions()->detach($permissionIds);
        }
    }
};
