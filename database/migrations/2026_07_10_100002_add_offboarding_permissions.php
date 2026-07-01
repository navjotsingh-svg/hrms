<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['slug' => 'offboarding.apply', 'name' => 'Submit Resignation', 'module' => 'offboarding', 'description' => 'Submit resignation requests'],
            ['slug' => 'offboarding.approve', 'name' => 'Approve Resignation', 'module' => 'offboarding', 'description' => 'Approve or reject resignation requests'],
            ['slug' => 'offboarding.manage', 'name' => 'Manage Offboarding', 'module' => 'offboarding', 'description' => 'Manage clearance, asset return, and exit cases'],
            ['slug' => 'clearance.review', 'name' => 'Review Clearance', 'module' => 'offboarding', 'description' => 'Sign off department clearance items'],
            ['slug' => 'offboarding.fnf.manage', 'name' => 'Manage F&F Settlement', 'module' => 'offboarding', 'description' => 'Process full and final settlements'],
        ];

        foreach ($definitions as $definition) {
            Permission::updateOrCreate(['slug' => $definition['slug']], $definition);
        }

        $permissionIds = Permission::query()
            ->whereIn('slug', collect($definitions)->pluck('slug'))
            ->pluck('id');

        Role::query()
            ->whereIn('slug', [Role::SLUG_COMPANY_ADMIN, Role::SLUG_HR_MANAGER])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }

    public function down(): void
    {
        $slugs = [
            'offboarding.apply', 'offboarding.approve', 'offboarding.manage',
            'clearance.review', 'offboarding.fnf.manage',
        ];

        $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            \DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
            Permission::query()->whereIn('slug', $slugs)->delete();
        }
    }
};
