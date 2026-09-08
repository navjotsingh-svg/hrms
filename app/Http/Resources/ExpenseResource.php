<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_date' => $this->expense_date?->toDateString(),
            'expense_date_label' => $this->expense_date?->format('d M Y'),
            'created_at_label' => $this->created_at?->format('d M Y'),
            'merchant' => $this->merchant,
            'amount' => (float) $this->amount,
            'amount_label' => '₹'.number_format((float) $this->amount, 2),
            'description' => $this->description,
            'reference_number' => $this->reference_number,
            'claim_reimbursement' => (bool) $this->claim_reimbursement,
            'is_independent' => (bool) $this->is_independent,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'payout_status' => $this->payout_status,
            'payout_status_label' => ucfirst($this->payout_status),
            'paid_at_label' => $this->paid_at?->labelStack(),
            'review_notes' => $this->review_notes,
            'reviewed_at_label' => $this->reviewed_at?->labelStack(),
            'expense_type' => new ExpenseTypeResource($this->whenLoaded('expenseType')),
            'expense_group_id' => $this->expense_group_id,
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, fn () => [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ]),
            'attachments' => $this->when($this->relationLoaded('attachments'), fn () => $this->attachments->map(fn ($file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'file_url' => $file->fileUrl(),
            ])),
            'can_submit' => $request->user()?->canEditOwnExpense($this->resource)
                && $this->status === 'draft'
                && $this->is_independent,
            'can_edit' => $request->user()?->canEditOwnExpense($this->resource) ?? false,
            'can_cancel' => $request->user()?->canEditOwnExpense($this->resource)
                && in_array($this->status, ['draft', 'pending'], true)
                && $this->is_independent,
            'can_review' => $request->user()?->canReviewExpense($this->resource) ?? false,
            'can_mark_paid' => $request->user()?->canMarkExpensePaid($this->resource) ?? false,
        ];
    }
}
