<?php

namespace App\Services;

use App\Models\WeeklyOffDay;
use Illuminate\Support\Facades\DB;

class WeeklyOffService
{
    public function getForCompany(int $companyId): array
    {
        $weekdays = WeeklyOffDay::query()
            ->where('company_id', $companyId)
            ->orderBy('weekday')
            ->pluck('weekday')
            ->map(fn ($weekday) => (int) $weekday)
            ->values()
            ->all();

        return [
            'weekdays' => $weekdays,
            'labels' => array_map(fn ($weekday) => WeeklyOffDay::label($weekday), $weekdays),
        ];
    }

    public function syncForCompany(int $companyId, array $weekdays): array
    {
        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        $weekdays = array_values(array_filter($weekdays, fn ($weekday) => $weekday >= 0 && $weekday <= 6));

        DB::transaction(function () use ($companyId, $weekdays) {
            WeeklyOffDay::query()->where('company_id', $companyId)->delete();

            foreach ($weekdays as $weekday) {
                WeeklyOffDay::create([
                    'company_id' => $companyId,
                    'weekday' => $weekday,
                ]);
            }
        });

        return $this->getForCompany($companyId);
    }
}
