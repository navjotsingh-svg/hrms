<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_regularization_requests', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('employee_id');
            $table->index(['company_id', 'batch_id', 'status'], 'att_reg_company_batch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_regularization_requests', function (Blueprint $table) {
            $table->dropIndex('att_reg_company_batch_status_idx');
            $table->dropColumn('batch_id');
        });
    }
};
