<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = [
        'slug' => 'home.moments.comment',
        'name' => 'Comment on Moments',
        'module' => 'home',
        'description' => 'Add comments on company moments posts',
    ];

    private const ROLE_SLUGS = [
        Role::SLUG_EMPLOYEE,
        Role::SLUG_COMPANY_ADMIN,
        Role::SLUG_HR_MANAGER,
    ];

    public function up(): void
    {
        Permission::updateOrCreate(['slug' => self::PERMISSION['slug']], self::PERMISSION);

        Schema::create('company_moment_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_moment_id')->constrained('company_moments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(['company_moment_id', 'created_at']);
        });

        $permissionId = Permission::query()->where('slug', self::PERMISSION['slug'])->value('id');

        if ($permissionId) {
            foreach (self::ROLE_SLUGS as $roleSlug) {
                $role = Role::query()->where('slug', $roleSlug)->first();

                if ($role) {
                    $role->permissions()->syncWithoutDetaching([$permissionId]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_moment_comments');

        $permission = Permission::query()->where('slug', self::PERMISSION['slug'])->first();

        if ($permission) {
            foreach (Role::query()->where('scope', 'company')->get() as $role) {
                $role->permissions()->detach($permission->id);
            }

            $permission->delete();
        }
    }
};
