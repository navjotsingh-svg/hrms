<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('level', 20)->default('individual')->after('company_id');
            $table->foreignId('department_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->foreignId('parent_goal_id')->nullable()->after('department_id')->constrained('goals')->nullOnDelete();
        });

        DB::statement('ALTER TABLE goals MODIFY employee_id BIGINT UNSIGNED NULL');

        Schema::table('goals', function (Blueprint $table) {
            $table->index(['company_id', 'level', 'status']);
            $table->index(['parent_goal_id']);
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'level', 'status']);
            $table->dropIndex(['parent_goal_id']);
            $table->dropConstrainedForeignId('parent_goal_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('level');
        });

        DB::statement('ALTER TABLE goals MODIFY employee_id BIGINT UNSIGNED NOT NULL');
    }
};
