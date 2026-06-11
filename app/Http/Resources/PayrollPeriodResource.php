<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'type' => $this->type,
            'label' => $this->label(),
            'status' => $this->status,
            'payslips_count' => $this->whenCounted('payslips'),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'processed_by' => $this->whenLoaded('processedBy', fn () => [
                'id' => $this->processedBy->id,
                'name' => $this->processedBy->name,
            ]),
        ];
    }
}
