<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedTinyInteger('attendance_regularization_previous_month_cutoff_day')
                ->nullable()
                ->after('attendance_require_punch_photo');
            $table->unsignedSmallInteger('attendance_regularization_max_requests_per_month')
                ->nullable()
                ->after('attendance_regularization_previous_month_cutoff_day');
            $table->boolean('attendance_regularization_block_current_day_until_complete')
                ->nullable()
                ->after('attendance_regularization_max_requests_per_month');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'attendance_regularization_previous_month_cutoff_day',
                'attendance_regularization_max_requests_per_month',
                'attendance_regularization_block_current_day_until_complete',
            ]);
        });
    }
};
