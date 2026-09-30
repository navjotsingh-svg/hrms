<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exit_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('exit_cases', 'direct_report_employee_ids')) {
                $table->json('direct_report_employee_ids')->nullable()->after('successor_manager_employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exit_cases', function (Blueprint $table) {
            if (Schema::hasColumn('exit_cases', 'direct_report_employee_ids')) {
                $table->dropColumn('direct_report_employee_ids');
            }
        });
    }
};
