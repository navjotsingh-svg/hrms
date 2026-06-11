<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'annual_quota' => $this->annual_quota,
            'max_days_per_request' => $this->max_days_per_request,
            'max_days_per_month' => $this->max_days_per_month,
            'is_hourly_leave' => $this->is_hourly_leave,
            'max_hours_per_month' => $this->isHourlyLeave() ? $this->max_hours_per_month : null,
            'allowed_hourly_durations' => $this->isHourlyLeave()
                ? $this->allowedHourlyDurations()
                : null,
            'application_policy_label' => $this->applicationPolicyLabel(),
            'quota_unit' => $this->quotaUnit(),
            'is_paid' => $this->is_paid,
            'requires_proof' => $this->requires_proof,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'is_comp_off' => $this->isCompOff(),
        ];
    }
}
