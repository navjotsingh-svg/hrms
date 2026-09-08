<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'assistant.use'],
            [
                'name' => 'Use HR Assistant',
                'module' => 'assistant',
                'description' => 'Chat with the HR assistant about your own HR information',
            ]
        );

        Role::query()
            ->where('scope', 'company')
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));
    }

    public function down(): void
    {
        $permissionId = Permission::query()->where('slug', 'assistant.use')->value('id');

        if (! $permissionId) {
            return;
        }

        \DB::table('role_permission')->where('permission_id', $permissionId)->delete();
        Permission::query()->whereKey($permissionId)->delete();
    }
};
