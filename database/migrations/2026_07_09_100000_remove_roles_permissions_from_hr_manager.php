<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', ['roles.view', 'roles.manage'])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $hrRoleIds = Role::query()
            ->where('slug', Role::SLUG_HR_MANAGER)
            ->pluck('id');

        if ($hrRoleIds->isNotEmpty()) {
            if (Schema::hasTable('role_permission')) {
                DB::table('role_permission')
                    ->whereIn('role_id', $hrRoleIds)
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }

            if (Schema::hasTable('company_role_permissions')) {
                DB::table('company_role_permissions')
                    ->whereIn('role_id', $hrRoleIds)
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Permissions are restored by re-running RoleSeeder if needed.
    }
};
