<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        if (Schema::hasColumn('leave_types', 'allows_hourly_leave')) {
            DB::table('leave_types')->update([
                'is_hourly_leave' => DB::raw('allows_hourly_leave'),
            ]);

            if (Schema::hasColumn('leave_types', 'max_hours_per_request')) {
                DB::table('leave_types')
                    ->where('allows_hourly_leave', true)
                    ->update([
                        'max_hours_per_month' => DB::raw('COALESCE(max_hours_per_request, 2)'),
                    ]);
            }
        }

        $clUpdates = [
            'is_hourly_leave' => false,
            'max_hours_per_month' => null,
        ];

        if (Schema::hasColumn('leave_types', 'allows_hourly_leave')) {
            $clUpdates['allows_hourly_leave'] = false;
        }

        if (Schema::hasColumn('leave_types', 'max_hours_per_request')) {
            $clUpdates['max_hours_per_request'] = null;
        }

        if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            $clUpdates['allowed_hourly_durations'] = null;
        }

        DB::table('leave_types')
            ->where('code', 'CL')
            ->update($clUpdates);

        $legacyColumns = array_values(array_filter([
            Schema::hasColumn('leave_types', 'allows_hourly_leave') ? 'allows_hourly_leave' : null,
            Schema::hasColumn('leave_types', 'max_hours_per_request') ? 'max_hours_per_request' : null,
        ]));

        if ($legacyColumns !== []) {
            Schema::table('leave_types', function (Blueprint $table) use ($legacyColumns) {
                $table->dropColumn($legacyColumns);
            });
        }

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $exists = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('code', 'HL')
                ->exists();

            if ($exists) {
                $hourlyUpdates = [
                    'is_hourly_leave' => true,
                    'max_hours_per_month' => DB::raw('COALESCE(max_hours_per_month, 4)'),
                    'max_days_per_request' => 2,
                    'annual_quota' => DB::raw('COALESCE(annual_quota, 3)'),
                    'status' => 'active',
                ];

                if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
                    $hourlyUpdates['allowed_hourly_durations'] = json_encode([60, 120]);
                }

                DB::table('leave_types')
                    ->where('company_id', $companyId)
                    ->where('code', 'HL')
                    ->update($hourlyUpdates);

                continue;
            }

            $insert = [
                'company_id' => $companyId,
                'name' => 'Hourly Leave',
                'code' => 'HL',
                'annual_quota' => 3,
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
        if (! Schema::hasColumn('leave_types', 'allows_hourly_leave')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->boolean('allows_hourly_leave')->default(false)->after('max_days_per_month');
                $table->unsignedTinyInteger('max_hours_per_request')->nullable()->after('allows_hourly_leave');
            });
        }

        if (Schema::hasColumn('leave_types', 'is_hourly_leave')) {
            DB::table('leave_types')->update([
                'allows_hourly_leave' => DB::raw('is_hourly_leave'),
            ]);
        }

        $dropColumns = array_values(array_filter([
            Schema::hasColumn('leave_types', 'is_hourly_leave') ? 'is_hourly_leave' : null,
            Schema::hasColumn('leave_types', 'max_hours_per_month') ? 'max_hours_per_month' : null,
        ]));

        if ($dropColumns !== []) {
            Schema::table('leave_types', function (Blueprint $table) use ($dropColumns) {
                $table->dropColumn($dropColumns);
            });
        }
    }
};
