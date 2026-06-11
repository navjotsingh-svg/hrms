<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date?->toDateString(),
            'date_label' => $this->date?->format('d M Y'),
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
        ];
    }
}
