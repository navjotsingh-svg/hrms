<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            if (DB::table('roles')->count() === 0) {
                (new RoleSeeder)->run();
            }

            $roleMap = DB::table('roles')->pluck('id', 'slug');

            foreach (DB::table('users')->orderBy('id')->lazy() as $user) {
                $slug = $user->role ?: 'company_admin';
                $roleId = $roleMap[$slug] ?? $roleMap['company_admin'] ?? null;

                if ($roleId) {
                    DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
                }
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('company_admin')->after('email');
        });

        $roleMap = DB::table('roles')->pluck('slug', 'id');

        DB::table('users')->orderBy('id')->each(function ($user) use ($roleMap) {
            DB::table('users')->where('id', $user->id)->update([
                'role' => $roleMap[$user->role_id] ?? 'company_admin',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
