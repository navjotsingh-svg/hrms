<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_types')
            ->whereIn('code', ['HL', 'SHL'])
            ->update(['code' => 'SHL']);

        DB::table('leave_types')
            ->where('code', 'SHL')
            ->update([
                'name' => 'Short Leave',
                'annual_quota' => DB::raw('CASE WHEN annual_quota <= 10 THEN annual_quota * 8 ELSE annual_quota END'),
            ]);

        $shortLeaveTypes = DB::table('leave_types')
            ->where('code', 'SHL')
            ->get(['id', 'annual_quota']);

        foreach ($shortLeaveTypes as $type) {
            DB::table('employee_leave_balances')
                ->where('leave_type_id', $type->id)
                ->update([
                    'used' => DB::raw('ROUND(used * 8, 2)'),
                    'pending' => DB::raw('ROUND(pending * 8, 2)'),
                    'allocated' => $type->annual_quota,
                ]);
        }
    }

    public function down(): void
    {
        $shortLeaveTypes = DB::table('leave_types')
            ->where('is_hourly_leave', true)
            ->get(['id', 'annual_quota']);

        foreach ($shortLeaveTypes as $type) {
            DB::table('employee_leave_balances')
                ->where('leave_type_id', $type->id)
                ->update([
                    'used' => DB::raw('ROUND(used / 8, 3)'),
                    'pending' => DB::raw('ROUND(pending / 8, 3)'),
                ]);
        }

        DB::table('leave_types')
            ->where('is_hourly_leave', true)
            ->update([
                'name' => 'Hourly Leave',
                'annual_quota' => DB::raw('CASE WHEN annual_quota >= 8 THEN annual_quota / 8 ELSE annual_quota END'),
            ]);

        DB::table('leave_types')
            ->where('is_hourly_leave', true)
            ->where('code', 'SHL')
            ->update(['code' => 'HL']);
    }
};
