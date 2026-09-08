<?php

namespace App\Http\Resources;

use App\Models\LeaveRequestDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'from_date_label' => $this->from_date?->format('d M Y'),
            'to_date_label' => $this->to_date?->format('d M Y'),
            'dates_label' => $this->formatDatesLabel(),
            'total_days' => (float) $this->total_days,
            'total_days_label' => $this->formatTotalDaysLabel(),
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewed_at_label' => $this->reviewed_at?->labelStack(),
            'created_at_label' => $this->created_at?->labelStack(),
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'applied_by' => $this->when($this->relationLoaded('appliedBy'), fn () => [
                'id' => $this->appliedBy->id,
                'name' => $this->appliedBy->name,
            ]),
            'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, fn () => [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ]),
            'days' => $this->when($this->relationLoaded('days'), fn () => $this->days->map(fn ($day) => [
                'date' => $day->date->toDateString(),
                'date_label' => $day->date->format('d M Y'),
                'session' => $day->session,
                'session_label' => $day->sessionLabel(),
                'duration_minutes' => $day->duration_minutes,
                'duration_label' => $day->duration_minutes
                    ? LeaveRequestDay::formatDurationLabel($day->duration_minutes)
                    : null,
                'day_value' => (float) $day->day_value,
            ])),
            'attachments' => $this->when($this->relationLoaded('attachments'), fn () => $this->attachments->map(fn ($file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'file_url' => $file->fileUrl(),
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
            ])),
            'can_review' => $request->user()?->canReviewLeaveRequest($this->resource) ?? false,
            'can_cancel' => $request->user()?->canCancelLeaveRequest($this->resource) ?? false,
            'can_upload_proof' => $request->user()?->canUploadLeaveProof($this->resource) ?? false,
            'can_bypass_proof' => $request->user()?->canBypassLeaveProofRequirement($this->resource) ?? false,
            'proof_required' => (bool) $this->whenLoaded('leaveType', fn () => $this->leaveType?->requires_proof),
            'proof_missing' => $this->when(
                $this->relationLoaded('leaveType') && $this->relationLoaded('attachments'),
                fn () => (bool) $this->leaveType?->requires_proof && $this->attachments->isEmpty(),
            ),
        ];
    }

    private function formatDatesLabel(): string
    {
        if (! $this->from_date) {
            return '—';
        }

        if (! $this->to_date || $this->from_date->equalTo($this->to_date)) {
            return $this->from_date->format('d M Y');
        }

        return $this->from_date->format('d M Y').' to '.$this->to_date->format('d M Y');
    }

    private function formatTotalDaysLabel(): string
    {
        if ($this->relationLoaded('leaveType') && $this->leaveType?->isHourlyLeave()) {
            $hours = (float) $this->total_days;
            $formatted = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

            if ($this->relationLoaded('days') && $this->days->count() === 1) {
                $day = $this->days->first();

                if ($day->duration_minutes) {
                    return sprintf('%s hour(s) (%s)', $formatted, LeaveRequestDay::formatDurationLabel($day->duration_minutes));
                }
            }

            return $formatted === '1' ? '1 hour' : "{$formatted} hour(s)";
        }

        $days = (float) $this->total_days;

        if ($this->relationLoaded('days') && $this->days->count() === 1) {
            $day = $this->days->first();

            if ($day->session === LeaveRequestDay::SESSION_HOURLY && $day->duration_minutes) {
                return sprintf(
                    '%s (%s)',
                    rtrim(rtrim(number_format($days, 3, '.', ''), '0'), '.'),
                    LeaveRequestDay::formatDurationLabel($day->duration_minutes),
                );
            }
        }

        $formatted = rtrim(rtrim(number_format($days, 3, '.', ''), '0'), '.');

        return $formatted === '1' ? '1 day' : "{$formatted} day(s)";
    }
}
