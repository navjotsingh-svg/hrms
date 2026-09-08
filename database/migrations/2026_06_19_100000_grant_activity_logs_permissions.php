<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::updateOrCreate(
            ['slug' => 'logs.view'],
            [
                'name' => 'View Activity Logs',
                'module' => 'logs',
                'description' => 'View company activity and audit logs',
            ]
        );

        $permissionId = Permission::query()->where('slug', 'logs.view')->value('id');

        if (! $permissionId) {
            return;
        }

        foreach ([Role::SLUG_COMPANY_ADMIN, Role::SLUG_HR_MANAGER, Role::SLUG_SUPER_ADMIN] as $roleSlug) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if ($role) {
                $role->permissions()->syncWithoutDetaching([$permissionId]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = Permission::query()->where('slug', 'logs.view')->value('id');

        if ($permissionId) {
            foreach (Role::query()->where('scope', '!=', 'platform')->orWhere('scope', 'platform')->get() as $role) {
                $role->permissions()->detach($permissionId);
            }

            Permission::query()->where('id', $permissionId)->delete();
        }
    }
};
