<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function dayLeaveDefaults(): array
    {
        return [
            'CL' => ['name' => 'Casual Leave', 'annual_quota' => 12, 'max_days_per_request' => 2, 'max_days_per_month' => 2, 'requires_proof' => false, 'color' => '#3b82f6', 'sort_order' => 1],
            'SL' => ['name' => 'Sick Leave', 'annual_quota' => 6, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => true, 'color' => '#ef4444', 'sort_order' => 2],
            'EL' => ['name' => 'Earned Leave', 'annual_quota' => 15, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => false, 'color' => '#22c55e', 'sort_order' => 3],
            'MAT' => ['name' => 'Maternity Leave', 'annual_quota' => 182, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => true, 'color' => '#ec4899', 'sort_order' => 4],
            'PAT' => ['name' => 'Paternity Leave', 'annual_quota' => 15, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => true, 'color' => '#6366f1', 'sort_order' => 5],
            'PINK' => ['name' => 'Pink Leave', 'annual_quota' => 12, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => false, 'color' => '#f472b6', 'sort_order' => 6],
            'BL' => ['name' => 'Bereavement Leave', 'annual_quota' => 5, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => false, 'color' => '#6b7280', 'sort_order' => 7],
            'MRL' => ['name' => 'Marriage Leave', 'annual_quota' => 3, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => true, 'color' => '#f59e0b', 'sort_order' => 8],
            'COMP' => ['name' => 'Comp Off', 'annual_quota' => 0, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => false, 'color' => '#14b8a6', 'sort_order' => 9],
            'LOP' => ['name' => 'Loss of Pay', 'annual_quota' => null, 'max_days_per_request' => null, 'max_days_per_month' => null, 'requires_proof' => false, 'color' => '#64748b', 'sort_order' => 10, 'is_paid' => false],
        ];
    }

    private function dayLeaveUpdate(array $defaults): array
    {
        $update = [
            'name' => $defaults['name'],
            'annual_quota' => $defaults['annual_quota'],
            'max_days_per_request' => $defaults['max_days_per_request'],
            'max_days_per_month' => $defaults['max_days_per_month'],
            'requires_proof' => $defaults['requires_proof'],
            'color' => $defaults['color'],
            'sort_order' => $defaults['sort_order'],
            'is_paid' => $defaults['is_paid'] ?? true,
        ];

        if (Schema::hasColumn('leave_types', 'is_hourly_leave')) {
            $update['is_hourly_leave'] = false;
        }

        if (Schema::hasColumn('leave_types', 'max_hours_per_month')) {
            $update['max_hours_per_month'] = null;
        }

        if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            $update['allowed_hourly_durations'] = null;
        }

        return $update;
    }

    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $corruptedTypeIds = Schema::hasColumn('leave_types', 'is_hourly_leave')
            ? DB::table('leave_types')
                ->where('is_hourly_leave', true)
                ->whereNotIn('code', ['SHL', 'HL'])
                ->pluck('id', 'code')
            : collect();

        foreach ($this->dayLeaveDefaults() as $code => $defaults) {
            DB::table('leave_types')
                ->where('code', $code)
                ->update($this->dayLeaveUpdate($defaults));
        }

        $shortLeaveUpdate = [
            'name' => 'Short Leave',
            'code' => 'SHL',
            'annual_quota' => 24,
            'max_days_per_request' => 2,
            'max_days_per_month' => null,
            'requires_proof' => false,
            'color' => '#0ea5e9',
            'sort_order' => 11,
            'is_paid' => true,
        ];

        if (Schema::hasColumn('leave_types', 'is_hourly_leave')) {
            $shortLeaveUpdate['is_hourly_leave'] = true;
        }

        if (Schema::hasColumn('leave_types', 'max_hours_per_month')) {
            $shortLeaveUpdate['max_hours_per_month'] = 4;
        }

        if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
            $shortLeaveUpdate['allowed_hourly_durations'] = json_encode([60, 120]);
        }

        DB::table('leave_types')
            ->whereIn('code', ['SHL', 'HL'])
            ->update($shortLeaveUpdate);

        if (Schema::hasColumn('leave_types', 'is_hourly_leave')) {
            $nonShortLeaveUpdates = ['is_hourly_leave' => false];

            if (Schema::hasColumn('leave_types', 'max_hours_per_month')) {
                $nonShortLeaveUpdates['max_hours_per_month'] = null;
            }

            if (Schema::hasColumn('leave_types', 'allowed_hourly_durations')) {
                $nonShortLeaveUpdates['allowed_hourly_durations'] = null;
            }

            DB::table('leave_types')
                ->whereNotIn('code', ['SHL'])
                ->where('is_hourly_leave', true)
                ->update($nonShortLeaveUpdates);
        }

        $dayTypeIds = DB::table('leave_types')
            ->whereIn('code', array_keys($this->dayLeaveDefaults()))
            ->pluck('id', 'code');

        foreach ($dayTypeIds as $code => $typeId) {
            $quota = $this->dayLeaveDefaults()[$code]['annual_quota'];
            $updates = ['allocated' => $quota ?? 0];

            if ($corruptedTypeIds->has($code)) {
                $updates['used'] = DB::raw('ROUND(used / 8, 3)');
                $updates['pending'] = DB::raw('ROUND(pending / 8, 3)');
            }

            DB::table('employee_leave_balances')
                ->where('leave_type_id', $typeId)
                ->update($updates);
        }

        $shortLeaveIds = DB::table('leave_types')->where('code', 'SHL')->pluck('id');

        foreach ($shortLeaveIds as $typeId) {
            DB::table('employee_leave_balances')
                ->where('leave_type_id', $typeId)
                ->update([
                    'allocated' => 24,
                ]);
        }

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $companyId) {
            $duplicateHl = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('code', 'HL')
                ->exists();

            $hasShl = DB::table('leave_types')
                ->where('company_id', $companyId)
                ->where('code', 'SHL')
                ->exists();

            if ($duplicateHl && $hasShl) {
                DB::table('leave_types')
                    ->where('company_id', $companyId)
                    ->where('code', 'HL')
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Data repair migration — no rollback.
    }
};
