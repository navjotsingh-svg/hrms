<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResignationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'proposed_last_working_date' => $this->proposed_last_working_date?->format('d M Y'),
            'approved_last_working_date' => $this->approved_last_working_date?->format('d M Y'),
            'notice_period_days' => $this->notice_period_days,
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
            'exit_case' => $this->when($this->relationLoaded('exitCase') && $this->exitCase, fn () => [
                'id' => $this->exitCase->id,
                'stage' => $this->exitCase->stage,
                'stage_label' => $this->exitCase->stageLabel(),
            ]),
            'can_review' => $request->user()?->canReviewResignationRequest($this->resource) ?? false,
            'can_cancel' => $request->user()?->canCancelResignationRequest($this->resource) ?? false,
        ];
    }
}
