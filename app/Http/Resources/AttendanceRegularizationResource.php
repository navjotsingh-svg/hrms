<?php

namespace App\Http\Resources;

use App\Services\AttendanceRegularizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRegularizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'attendance_date_label' => $this->attendance_date?->format('d M Y'),
            'requested_punch_in' => $this->requested_punch_in?->format('H:i'),
            'requested_punch_out' => $this->requested_punch_out?->format('H:i'),
            'requested_punch_in_label' => $this->requested_punch_in?->format('h:i A'),
            'requested_punch_out_label' => $this->requested_punch_out?->format('h:i A'),
            ...app(AttendanceRegularizationService::class)->formatOriginalPunchFields($this->resource),
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'review_notes' => $this->review_notes,
            'reviewed_at_label' => $this->reviewed_at?->format('d M Y, h:i A'),
            'created_at_label' => $this->created_at?->format('d M Y, h:i A'),
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'applied_by' => $this->when($this->relationLoaded('appliedBy'), fn () => [
                'id' => $this->appliedBy->id,
                'name' => $this->appliedBy->name,
            ]),
            'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, fn () => [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ]),
            'can_review' => $request->user()?->canReviewRegularizationRequest($this->resource) ?? false,
            'can_cancel' => $request->user()?->canCancelRegularizationRequest($this->resource) ?? false,
        ];
    }
}
