<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        if (Schema::hasColumn('companies', 'attendance_regularization_previous_month_cutoff_day')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $cutoff = $table->unsignedTinyInteger('attendance_regularization_previous_month_cutoff_day')->nullable();
            if (Schema::hasColumn('companies', 'attendance_require_punch_photo')) {
                $cutoff->after('attendance_require_punch_photo');
            }

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
        if (! Schema::hasTable('companies')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('companies', 'attendance_regularization_previous_month_cutoff_day')
                ? 'attendance_regularization_previous_month_cutoff_day'
                : null,
            Schema::hasColumn('companies', 'attendance_regularization_max_requests_per_month')
                ? 'attendance_regularization_max_requests_per_month'
                : null,
            Schema::hasColumn('companies', 'attendance_regularization_block_current_day_until_complete')
                ? 'attendance_regularization_block_current_day_until_complete'
                : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
