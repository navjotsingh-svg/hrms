<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_types', 'allows_hourly_leave')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->boolean('allows_hourly_leave')->default(false)->after('max_days_per_month');
            });
        }

        if (! Schema::hasColumn('leave_types', 'max_hours_per_request')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->unsignedTinyInteger('max_hours_per_request')->nullable()->after('max_days_per_month');
            });
        }

        if (! Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->json('allowed_hourly_durations')->nullable()->after('max_days_per_month');
            });
        }

        if (! Schema::hasColumn('leave_request_days', 'duration_minutes')) {
            Schema::table('leave_request_days', function (Blueprint $table) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('session');
            });
        }

        if (Schema::hasTable('leave_request_days') && Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE leave_request_days MODIFY session VARCHAR(20) NOT NULL DEFAULT 'full_day'");
                DB::statement('ALTER TABLE leave_request_days MODIFY day_value DECIMAL(5, 3) NOT NULL DEFAULT 1');
            } catch (\Throwable) {
                // Column types may already be updated on a retried migration.
            }
        }

        if (Schema::hasColumn('leave_types', 'allows_hourly_leave')) {
            $clUpdates = [
                'allows_hourly_leave' => true,
                'max_hours_per_request' => 2,
            ];

            if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
                $clUpdates['allowed_hourly_durations'] = json_encode([60, 120]);
            }

            DB::table('leave_types')
                ->where('code', 'CL')
                ->update($clUpdates);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_request_days', 'duration_minutes')) {
            Schema::table('leave_request_days', function (Blueprint $table) {
                $table->dropColumn('duration_minutes');
            });
        }

        $legacyColumns = array_values(array_filter([
            Schema::hasColumn('leave_types', 'allows_hourly_leave') ? 'allows_hourly_leave' : null,
            Schema::hasColumn('leave_types', 'max_hours_per_request') ? 'max_hours_per_request' : null,
            Schema::hasColumn('leave_types', 'allowed_hourly_durations') ? 'allowed_hourly_durations' : null,
        ]));

        if ($legacyColumns !== []) {
            Schema::table('leave_types', function (Blueprint $table) use ($legacyColumns) {
                $table->dropColumn($legacyColumns);
            });
        }
    }
};
