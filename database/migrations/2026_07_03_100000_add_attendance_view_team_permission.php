<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'attendance.view_team'],
            [
                'name' => 'View Team Attendance',
                'module' => 'attendance',
                'description' => 'View attendance for direct reports or the wider team',
            ]
        );

        Role::query()
            ->whereIn('slug', [
                Role::SLUG_COMPANY_ADMIN,
                Role::SLUG_HR_MANAGER,
                Role::SLUG_DEPARTMENT_HEAD,
                Role::SLUG_TEAM_LEAD,
            ])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    public function down(): void
    {
        $permissionId = Permission::query()->where('slug', 'attendance.view_team')->value('id');

        if (! $permissionId) {
            return;
        }

        \DB::table('role_permission')
            ->where('permission_id', $permissionId)
            ->delete();

        Permission::query()->whereKey($permissionId)->delete();
    }
};
