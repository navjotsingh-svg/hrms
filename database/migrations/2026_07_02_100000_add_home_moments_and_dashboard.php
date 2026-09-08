<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['slug' => 'home.view', 'name' => 'View Home', 'module' => 'home', 'description' => 'Access the Home section'],
        ['slug' => 'home.dashboard.view', 'name' => 'View Home Dashboard', 'module' => 'home', 'description' => 'View dashboard widgets and charts on Home'],
        ['slug' => 'home.dashboard.manage', 'name' => 'Manage Home Dashboard', 'module' => 'home', 'description' => 'Customize dashboard widget layout'],
        ['slug' => 'home.moments.view', 'name' => 'View Moments', 'module' => 'home', 'description' => 'View company moments feed'],
        ['slug' => 'home.moments.post', 'name' => 'Post Moments', 'module' => 'home', 'description' => 'Create posts in the moments feed'],
    ];

    private const HOME_PERMISSIONS = [
        'home.view',
        'home.dashboard.view',
        'home.dashboard.manage',
        'home.moments.view',
        'home.moments.post',
    ];

    private const ROLE_PERMISSIONS = [
        Role::SLUG_EMPLOYEE => ['home.view', 'home.moments.view'],
        Role::SLUG_COMPANY_ADMIN => self::HOME_PERMISSIONS,
        Role::SLUG_HR_MANAGER => self::HOME_PERMISSIONS,
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        Schema::create('company_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['post', 'birthday', 'work_anniversary', 'new_joinee']);
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->date('occasion_date')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'published_at']);
            $table->index(['company_id', 'type', 'occasion_date']);
        });

        Schema::create('company_moment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_moment_id')->constrained('company_moments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reaction', ['like', 'love', 'insightful', 'clap', 'note']);
            $table->timestamps();

            $table->unique(['company_moment_id', 'user_id']);
        });

        Schema::create('user_home_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('widget_key');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'widget_key']);
        });

        foreach (self::ROLE_PERMISSIONS as $roleSlug => $slugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::query()->whereIn('slug', $slugs)->pluck('id');
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_home_dashboard_widgets');
        Schema::dropIfExists('company_moment_reactions');
        Schema::dropIfExists('company_moments');

        $permissionIds = Permission::query()
            ->whereIn('slug', self::HOME_PERMISSIONS)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            foreach (Role::query()->where('scope', 'company')->get() as $role) {
                $role->permissions()->detach($permissionIds);
            }
        }

        Permission::query()->whereIn('slug', self::HOME_PERMISSIONS)->delete();
    }
};
