<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\WeeklyOffDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendancePolicyService
{
    public function holidaysForRange(int $companyId, Carbon $start, Carbon $end): Collection
    {
        return Holiday::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());
    }

    public function holidayOnDate(int $companyId, string $date): ?Holiday
    {
        return Holiday::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('date', $date)
            ->first();
    }

    public function weeklyOffWeekdays(int $companyId): array
    {
        $weekdays = WeeklyOffDay::query()
            ->where('company_id', $companyId)
            ->pluck('weekday')
            ->map(fn ($weekday) => (int) $weekday)
            ->values()
            ->all();

        return $weekdays !== [] ? $weekdays : [0];
    }

    public function isWeeklyOff(string $date, array $weekdays): bool
    {
        if ($weekdays === []) {
            return false;
        }

        return in_array(Carbon::parse($date)->dayOfWeek, $weekdays, true);
    }
}
