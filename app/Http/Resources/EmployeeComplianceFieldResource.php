<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeComplianceFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'field_type' => $this->field_type,
            'value' => $this->value,
            'status' => $this->status,
            'notes' => $this->notes,
            'can_resubmit' => $this->canBeResubmitted(),
            'is_locked' => $this->isLocked(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->submittedBy ? [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
                'role' => $this->submittedBy->role?->name,
            ] : null),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null),
        ];
    }
}
