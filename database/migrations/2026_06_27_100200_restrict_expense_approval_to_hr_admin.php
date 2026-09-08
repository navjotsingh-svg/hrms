<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROLE_SLUGS = [
        Role::SLUG_DEPARTMENT_HEAD,
        Role::SLUG_TEAM_LEAD,
    ];

    public function up(): void
    {
        $permissionId = Permission::query()
            ->where('slug', 'expenses.approve')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = Role::query()
            ->whereIn('slug', self::ROLE_SLUGS)
            ->pluck('id');

        DB::table('role_permission')
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permissionId)
            ->delete();
    }

    public function down(): void
    {
        $permissionId = Permission::query()
            ->where('slug', 'expenses.approve')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        Role::query()
            ->whereIn('slug', self::ROLE_SLUGS)
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permissionId]));
    }
};
