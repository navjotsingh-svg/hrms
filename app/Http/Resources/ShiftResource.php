<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'start_time' => $this->formatTimeForInput($this->start_time),
            'end_time' => $this->formatTimeForInput($this->end_time),
            'break_duration_minutes' => (int) $this->break_duration_minutes,
            'is_overnight' => (bool) $this->is_overnight,
            'description' => $this->description,
            'status' => $this->status,
            'time_range' => $this->time_range,
            'timing_summary' => $this->timing_summary,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatTimeForInput(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return substr($time, 0, 5);
    }
}
