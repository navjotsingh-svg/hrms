<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startTime = is_string($this->start_time)
            ? substr($this->start_time, 0, 5)
            : $this->start_time;
        $endTime = is_string($this->end_time)
            ? substr($this->end_time, 0, 5)
            : $this->end_time;

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'project_id' => $this->project_id,
            'work_date' => $this->work_date?->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hours' => (float) $this->hours,
            'hours_label' => $this->formatHoursLabel((float) $this->hours),
            'notes' => $this->notes,
            'done_today' => $this->done_today,
            'blockers' => $this->blockers,
            'plan_tomorrow' => $this->plan_tomorrow,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project?->id,
                'name' => $this->project?->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatHoursLabel(float $hours): string
    {
        $wholeHours = (int) floor($hours);
        $minutes = (int) round(($hours - $wholeHours) * 60);

        if ($wholeHours === 0) {
            return "{$minutes}m";
        }

        if ($minutes === 0) {
            return $wholeHours === 1 ? '1h' : "{$wholeHours}h";
        }

        return "{$wholeHours}h {$minutes}m";
    }
}
