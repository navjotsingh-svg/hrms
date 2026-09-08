<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'slug' => 'employees.assign_admin',
            'name' => 'Assign Company Admin',
            'module' => 'employees',
            'description' => 'Promote or remove company administrator access for employees',
        ],
    ];

    private const ROLE_PERMISSIONS = [
        Role::SLUG_COMPANY_ADMIN => [
            'employees.assign_admin',
            'roles.view',
            'roles.manage',
        ],
        Role::SLUG_HR_MANAGER => [
            'employees.assign_admin',
            'roles.view',
            'roles.manage',
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $permissionIds = Permission::query()
            ->whereIn('slug', collect(self::ROLE_PERMISSIONS)->flatten()->unique()->all())
            ->pluck('id', 'slug');

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $ids = collect($slugs)
                ->map(fn (string $slug) => $permissionIds[$slug] ?? null)
                ->filter()
                ->all();

            if ($ids !== []) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    public function down(): void
    {
        $assignAdmin = Permission::query()->where('slug', 'employees.assign_admin')->first();

        if ($assignAdmin) {
            foreach (Role::query()->where('scope', 'company')->get() as $role) {
                $role->permissions()->detach($assignAdmin->id);
            }

            $assignAdmin->delete();
        }
    }
};
