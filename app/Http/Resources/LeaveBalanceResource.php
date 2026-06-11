<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'allocated' => (float) $this->allocated,
            'used' => (float) $this->used,
            'pending' => (float) $this->pending,
            'adjusted' => (float) $this->adjusted,
            'available' => $this->available() === PHP_FLOAT_MAX ? null : round($this->available(), $this->leaveType?->usesHourQuota() ? 2 : 1),
            'balance_unit' => $this->leaveType?->quotaUnit() ?? 'days',
            'is_comp_off' => $this->relationLoaded('leaveType') && $this->leaveType?->isCompOff(),
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
        ];
    }
}
