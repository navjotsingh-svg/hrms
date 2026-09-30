<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exit_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('exit_cases', 'successor_manager_employee_id')) {
                $table->unsignedBigInteger('successor_manager_employee_id')->nullable()->after('employee_id');
                $table->index('successor_manager_employee_id', 'exit_cases_successor_manager_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exit_cases', function (Blueprint $table) {
            if (Schema::hasColumn('exit_cases', 'successor_manager_employee_id')) {
                $table->dropIndex('exit_cases_successor_manager_index');
                $table->dropColumn('successor_manager_employee_id');
            }
        });
    }
};
