<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            [
                'slug' => 'assets.manage',
                'name' => 'Manage Assets',
                'module' => 'assets',
                'description' => 'Create and organize asset types',
            ],
            [
                'slug' => 'assets.view',
                'name' => 'View Assets',
                'module' => 'assets',
                'description' => 'View asset type master',
            ],
        ];

        foreach ($definitions as $definition) {
            Permission::updateOrCreate(
                ['slug' => $definition['slug']],
                $definition
            );
        }

        $permissionIds = Permission::query()
            ->whereIn('slug', ['assets.manage', 'assets.view'])
            ->pluck('id');

        Role::query()
            ->whereIn('slug', [Role::SLUG_COMPANY_ADMIN, Role::SLUG_HR_MANAGER])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }

    public function down(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('slug', ['assets.manage', 'assets.view'])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        \DB::table('role_permission')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        Permission::query()
            ->whereIn('slug', ['assets.manage', 'assets.view'])
            ->delete();
    }
};
