<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::create('company_role_permissions', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['company_id', 'role_id', 'permission_id']);
        });

        Permission::updateOrCreate(
            ['slug' => 'roles.manage'],
            [
                'name' => 'Manage Roles',
                'module' => 'roles',
                'description' => 'Configure role permissions and create custom roles',
            ]
        );

        Permission::updateOrCreate(
            ['slug' => 'roles.view'],
            [
                'name' => 'View Roles',
                'module' => 'roles',
                'description' => 'View company roles and permission assignments',
            ]
        );

        $companyAdmin = Role::query()->where('slug', Role::SLUG_COMPANY_ADMIN)->first();

        if ($companyAdmin) {
            $permissionIds = Permission::query()
                ->whereIn('slug', ['roles.manage', 'roles.view'])
                ->pluck('id');

            $existing = DB::table('role_permission')
                ->where('role_id', $companyAdmin->id)
                ->pluck('permission_id');

            foreach ($permissionIds as $permissionId) {
                if (! $existing->contains($permissionId)) {
                    DB::table('role_permission')->insert([
                        'role_id' => $companyAdmin->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_role_permissions');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Permission::query()->whereIn('slug', ['roles.manage', 'roles.view'])->delete();
    }
};
