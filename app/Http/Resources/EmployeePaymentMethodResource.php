<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'payment_mode' => $this->payment_mode,
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'bank_address' => $this->bank_address,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'ifsc_code' => $this->ifsc_code,
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
