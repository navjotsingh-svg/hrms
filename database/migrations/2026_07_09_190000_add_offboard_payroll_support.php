<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payroll_periods MODIFY COLUMN type ENUM('regular', 'offboard') NOT NULL DEFAULT 'regular'");

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->foreignId('exit_case_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->unique('exit_case_id');
        });

        Schema::table('full_and_final_settlements', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')->nullable()->after('exit_case_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('full_and_final_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_period_id');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropUnique(['exit_case_id']);
            $table->dropConstrainedForeignId('exit_case_id');
            $table->dropConstrainedForeignId('employee_id');
        });

        DB::statement("ALTER TABLE payroll_periods MODIFY COLUMN type ENUM('regular') NOT NULL DEFAULT 'regular'");
    }
};
