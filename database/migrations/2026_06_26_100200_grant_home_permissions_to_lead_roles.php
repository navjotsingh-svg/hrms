<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const HOME_PERMISSIONS = [
        'home.view',
        'home.dashboard.view',
        'home.moments.view',
        'home.moments.post',
        'home.moments.comment',
    ];

    private const ROLE_SLUGS = [
        Role::SLUG_DEPARTMENT_HEAD,
        Role::SLUG_TEAM_LEAD,
    ];

    public function up(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', self::HOME_PERMISSIONS)
            ->pluck('id', 'slug');

        if ($permissionIds->isEmpty()) {
            return;
        }

        foreach (self::ROLE_SLUGS as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permissionIds->values());

            $overridePairs = DB::table('company_role_permissions')
                ->where('role_id', $role->id)
                ->select('company_id')
                ->distinct()
                ->pluck('company_id');

            foreach ($overridePairs as $companyId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('company_role_permissions')->insertOrIgnore([
                        'company_id' => $companyId,
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        //
    }
};
