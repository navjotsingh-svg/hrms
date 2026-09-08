<?php

use App\Models\AttendancePunch;
use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendance_regularization_requests', 'original_punch_in')) {
            return;
        }

        AttendanceRegularizationRequest::query()
            ->where(function ($query) {
                $query->whereNull('original_punch_in')
                    ->orWhereNull('original_punch_out');
            })
            ->orderBy('id')
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    if (! $request->employee_id || ! $request->attendance_date) {
                        continue;
                    }

                    $timezone = Employee::query()
                        ->whereKey($request->employee_id)
                        ->with('company:id,timezone')
                        ->first()
                        ?->company
                        ?->timezone ?: config('app.timezone');

                    $date = $request->attendance_date->toDateString();
                    $dayStart = Carbon::parse($date, $timezone)->startOfDay()->utc();
                    $dayEnd = Carbon::parse($date, $timezone)->endOfDay()->utc();

                    $punches = AttendancePunch::query()
                        ->where('employee_id', $request->employee_id)
                        ->whereBetween('punched_at', [$dayStart, $dayEnd])
                        ->where('source', '!=', AttendancePunch::SOURCE_REGULARIZATION)
                        ->orderBy('punched_at')
                        ->get();

                    $firstIn = $punches->firstWhere('punch_type', AttendancePunch::TYPE_IN);
                    $lastOut = $punches->where('punch_type', AttendancePunch::TYPE_OUT)->last();

                    $updates = [];

                    if (! $request->original_punch_in && $firstIn) {
                        $updates['original_punch_in'] = $firstIn->punched_at;
                    }

                    if (! $request->original_punch_out && $lastOut) {
                        $updates['original_punch_out'] = $lastOut->punched_at;
                    }

                    if ($updates !== []) {
                        $request->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        //
    }
};
