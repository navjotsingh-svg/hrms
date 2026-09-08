<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        if (! Schema::hasColumn('leave_types', 'is_hourly_leave')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->boolean('is_hourly_leave')->default(false)->after('max_days_per_month');
            });
        }

        if (! Schema::hasColumn('leave_types', 'max_hours_per_month')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->unsignedSmallInteger('max_hours_per_month')->nullable()->after('is_hourly_leave');
            });
        }

        if (! Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->json('allowed_hourly_durations')->nullable()->after('max_hours_per_month');
            });
        }

        if (Schema::hasTable('leave_request_days') && ! Schema::hasColumn('leave_request_days', 'duration_minutes')) {
            Schema::table('leave_request_days', function (Blueprint $table) {
                $table->unsignedSmallInteger('duration_minutes')->nullable()->after('session');
            });
        }

        if (Schema::hasColumn('leave_types', 'allows_hourly_leave') && Schema::hasColumn('leave_types', 'is_hourly_leave')) {
            DB::table('leave_types')
                ->where('is_hourly_leave', false)
                ->where('allows_hourly_leave', true)
                ->update(['is_hourly_leave' => true]);
        }

        if (Schema::hasColumn('leave_types', 'allows_hourly_leave')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('allows_hourly_leave');
            });
        }

        if (Schema::hasColumn('leave_types', 'max_hours_per_request')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('max_hours_per_request');
            });
        }

        $clUpdates = [
            'is_hourly_leave' => false,
            'max_hours_per_month' => null,
            'max_days_per_month' => DB::raw('COALESCE(max_days_per_month, 2)'),
            'max_days_per_request' => DB::raw('COALESCE(max_days_per_request, 2)'),
        ];

        if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            $clUpdates['allowed_hourly_durations'] = null;
        }

        DB::table('leave_types')
            ->where('code', 'CL')
            ->update($clUpdates);

        $shortLeaveUpdates = [
            'name' => 'Short Leave',
            'code' => 'SHL',
            'is_hourly_leave' => true,
            'max_hours_per_month' => DB::raw('COALESCE(max_hours_per_month, 4)'),
            'max_days_per_request' => DB::raw('COALESCE(max_days_per_request, 2)'),
            'max_days_per_month' => null,
            'status' => 'active',
        ];

        if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            $shortLeaveUpdates['allowed_hourly_durations'] = json_encode([60, 120]);
        }

        DB::table('leave_types')
            ->whereIn('code', ['HL', 'SHL'])
            ->update($shortLeaveUpdates);

        $nonShortLeaveUpdates = [
            'is_hourly_leave' => false,
            'max_hours_per_month' => null,
        ];

        if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            $nonShortLeaveUpdates['allowed_hourly_durations'] = null;
        }

        DB::table('leave_types')
            ->whereNotIn('code', ['SHL'])
            ->where('is_hourly_leave', true)
            ->update($nonShortLeaveUpdates);

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $hasShl = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('code', 'SHL')
                ->exists();

            if ($hasShl) {
                DB::table('leave_types')
                    ->where('company_id', $companyId)
                    ->where('code', 'HL')
                    ->delete();

                continue;
            }

            $hasHl = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('code', 'HL')
                ->exists();

            if ($hasHl) {
                DB::table('leave_types')
                    ->where('company_id', $companyId)
                    ->where('code', 'HL')
                    ->update(['code' => 'SHL', 'name' => 'Short Leave']);

                continue;
            }

            $insert = [
                'company_id' => $companyId,
                'name' => 'Short Leave',
                'code' => 'SHL',
                'annual_quota' => 24,
                'max_days_per_request' => 2,
                'max_days_per_month' => null,
                'is_hourly_leave' => true,
                'max_hours_per_month' => 4,
                'is_paid' => true,
                'requires_proof' => false,
                'color' => '#0ea5e9',
                'sort_order' => 11,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
                $insert['allowed_hourly_durations'] = json_encode([60, 120]);
            }

            DB::table('leave_types')->insert($insert);
        }
    }

    public function down(): void
    {
        // Repair migration — no rollback.
    }
};
