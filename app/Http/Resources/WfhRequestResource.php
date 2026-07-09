<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WfhRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $from = $this->from_date;
        $to = $this->to_date;

        return [
            'id' => $this->id,
            'from_date' => $from?->toDateString(),
            'to_date' => $to?->toDateString(),
            'from_date_label' => $from?->format('d M Y'),
            'to_date_label' => $to?->format('d M Y'),
            'dates_label' => $from?->equalTo($to)
                ? $from->format('d M Y')
                : ($from?->format('d M Y').' - '.$to?->format('d M Y')),
            'total_days' => $this->total_days,
            'total_days_label' => rtrim(rtrim(number_format((float) $this->total_days, 1, '.', ''), '0'), '.').' day(s)',
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'review_notes' => $this->review_notes,
            'reviewed_at_label' => $this->reviewed_at?->labelStack(),
            'created_at_label' => $this->created_at?->labelStack(),
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
            'attachments' => $this->when($this->relationLoaded('attachments'), fn () => $this->attachments->map(fn ($file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'file_url' => $file->fileUrl(),
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
            ])->values()->all()),
            'can_review' => $request->user()?->canReviewWfhRequest($this->resource) ?? false,
            'can_cancel' => $request->user()?->canCancelWfhRequest($this->resource) ?? false,
            'can_upload_proof' => $request->user()?->canUploadWfhAttachments($this->resource) ?? false,
        ];
    }
}
