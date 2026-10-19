<?php

namespace App\Http\Resources;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $from = $this->from_date ?? $this->date;
        $to = $this->to_date ?? $this->date;
        $duration = $this->duration ?? ($from && $to && $from->equalTo($to) ? Holiday::DURATION_SINGLE : Holiday::DURATION_RANGE);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'frequency' => $this->frequency,
            'frequency_label' => ucfirst((string) $this->frequency),
            'duration' => $duration,
            'duration_label' => match ($duration) {
                Holiday::DURATION_RANGE => 'Multiple Days',
                Holiday::DURATION_HALF => 'Half Day'.($this->halfSessionLabel() ? ' · '.$this->halfSessionLabel() : ''),
                default => 'Single Day',
            },
            'is_half_day' => $duration === Holiday::DURATION_HALF,
            'half_session' => $duration === Holiday::DURATION_HALF ? $this->half_session : null,
            'half_session_label' => $duration === Holiday::DURATION_HALF ? $this->halfSessionLabel() : null,
            'date' => $from?->toDateString(),
            'from_date' => $from?->toDateString(),
            'to_date' => $to?->toDateString(),
            'holiday_date' => $this->frequency === Holiday::FREQUENCY_VARIABLE && $duration !== Holiday::DURATION_RANGE
                ? $from?->toDateString()
                : null,
            'start_month' => $from ? (int) $from->format('n') : null,
            'start_day' => $from ? (int) $from->format('j') : null,
            'end_month' => $to ? (int) $to->format('n') : null,
            'end_day' => $to ? (int) $to->format('j') : null,
            'date_label' => $this->resource->displayDateLabel(),
            'type' => $this->type,
            'type_label' => match ($this->type) {
                'public' => 'Public',
                'company' => 'Company',
                'optional' => 'Optional',
                'other' => 'Other',
                default => ucfirst((string) $this->type),
            },
            'status' => $this->status,
            'description' => $this->description,
        ];
    }
}
