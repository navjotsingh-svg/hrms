<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WeeklyOffDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendancePolicyService
{
    public function holidaysForRange(int $companyId, Carbon $start, Carbon $end): Collection
    {
        $holidays = Holiday::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('from_date')
            ->get();

        $byDate = collect();

        foreach ($holidays as $holiday) {
            if ($holiday->isFixed()) {
                for ($year = $start->year; $year <= $end->year; $year++) {
                    [$rangeStart, $rangeEnd] = $holiday->resolvedBoundsForYear($year);
                    $clipStart = $rangeStart->greaterThan($start) ? $rangeStart : $start->copy()->startOfDay();
                    $clipEnd = $rangeEnd->lessThan($end) ? $rangeEnd : $end->copy()->startOfDay();

                    if ($clipStart->greaterThan($clipEnd)) {
                        continue;
                    }

                    foreach (CarbonPeriod::create($clipStart, $clipEnd) as $date) {
                        $byDate->put($date->toDateString(), $holiday);
                    }
                }

                continue;
            }

            if ($holiday->to_date->toDateString() < $start->toDateString()
                || $holiday->from_date->toDateString() > $end->toDateString()) {
                continue;
            }

            $rangeStart = Carbon::parse(max($start->toDateString(), $holiday->from_date->toDateString()));
            $rangeEnd = Carbon::parse(min($end->toDateString(), $holiday->to_date->toDateString()));

            foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                $byDate->put($date->toDateString(), $holiday);
            }
        }

        return $byDate;
    }

    public function holidayOnDate(int $companyId, string $date): ?Holiday
    {
        return Holiday::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->get()
            ->first(fn (Holiday $holiday) => $holiday->coversDateResolved($date));
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

    public function weeklyOffWeekdaysForEmployee(Employee $employee): array
    {
        $employee->loadMissing('weeklyOffDays');

        if (! $employee->usesCompanyWeeklyOff()) {
            $weekdays = $employee->weeklyOffDays
                ->pluck('weekday')
                ->map(fn ($weekday) => (int) $weekday)
                ->values()
                ->all();

            if ($weekdays !== []) {
                return $weekdays;
            }
        }

        return $this->weeklyOffWeekdays($employee->company_id);
    }

    /** @return array<int, string> */
    public function weeklyOffLabelsForEmployee(Employee $employee): array
    {
        return array_map(
            fn (int $weekday) => WeeklyOffDay::label($weekday),
            $this->weeklyOffWeekdaysForEmployee($employee),
        );
    }
}
