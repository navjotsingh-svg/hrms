<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::updateOrCreate(
            ['slug' => 'timesheets.submit'],
            [
                'name' => 'Submit Timesheets',
                'module' => 'timesheets',
                'description' => 'Log daily work hours against assigned projects',
            ],
        );

        $permissionId = Permission::query()
            ->where('slug', 'timesheets.submit')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleSlugs = [
            Role::SLUG_COMPANY_ADMIN,
            Role::SLUG_HR_MANAGER,
            Role::SLUG_DEPARTMENT_HEAD,
            Role::SLUG_TEAM_LEAD,
            Role::SLUG_EMPLOYEE,
        ];

        foreach ($roleSlugs as $slug) {
            $role = Role::query()->where('slug', $slug)->first();

            if ($role) {
                $role->permissions()->syncWithoutDetaching([$permissionId]);
            }
        }
    }

    public function down(): void
    {
        Permission::query()->where('slug', 'timesheets.submit')->delete();
    }
};
