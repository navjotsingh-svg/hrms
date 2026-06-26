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
            'status_label' => $this->statusLabel(),
            'is_paid' => $this->isPaid(),
            'payslips_count' => $this->whenCounted('payslips'),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'processed_at_label' => $this->processed_at?->format('d M Y, h:i A'),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'paid_at_label' => $this->paid_at?->format('d M Y, h:i A'),
            'processed_by' => $this->whenLoaded('processedBy', fn () => [
                'id' => $this->processedBy->id,
                'name' => $this->processedBy->name,
            ]),
            'paid_by' => $this->whenLoaded('paidBy', fn () => $this->paidBy ? [
                'id' => $this->paidBy->id,
                'name' => $this->paidBy->name,
            ] : null),
        ];
    }
}
