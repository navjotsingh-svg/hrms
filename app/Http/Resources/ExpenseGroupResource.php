<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expenses = $this->whenLoaded('expenses');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'from_date_label' => $this->from_date?->format('d M Y'),
            'to_date_label' => $this->to_date?->format('d M Y'),
            'created_at_label' => $this->created_at?->format('d M Y'),
            'travel_advance_amount' => (float) $this->travel_advance_amount,
            'travel_advance_label' => '₹'.number_format((float) $this->travel_advance_amount, 2),
            'total_amount' => $this->relationLoaded('expenses') ? $this->totalAmount() : null,
            'total_amount_label' => $this->relationLoaded('expenses')
                ? '₹'.number_format($this->totalAmount(), 2)
                : null,
            'approved_reimbursable_amount' => $this->relationLoaded('expenses') ? $this->approvedReimbursableAmount() : null,
            'approved_reimbursable_label' => $this->relationLoaded('expenses')
                ? '₹'.number_format($this->approvedReimbursableAmount(), 2)
                : null,
            'net_adjustment' => $this->relationLoaded('expenses') ? $this->netAdjustment() : null,
            'net_adjustment_label' => $this->relationLoaded('expenses')
                ? '₹'.number_format($this->netAdjustment(), 2)
                : null,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'review_notes' => $this->review_notes,
            'reviewed_at_label' => $this->reviewed_at?->format('d M Y, h:i A'),
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, fn () => [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ]),
            'expenses' => ExpenseResource::collection($this->whenLoaded('expenses')),
            'expense_count' => $this->when(isset($this->expenses_count), fn () => $this->expenses_count),
            'can_submit' => $this->status === 'draft'
                && ($request->user()?->canEditOwnExpenseGroup($this->resource) ?? false),
            'can_edit' => $request->user()?->canEditOwnExpenseGroup($this->resource) ?? false,
            'can_cancel' => in_array($this->status, ['draft', 'pending'], true)
                && ($request->user()?->canEditOwnExpenseGroup($this->resource) ?? false),
            'can_add_expense' => $this->status === 'draft'
                && ($request->user()?->canEditOwnExpenseGroup($this->resource) ?? false),
            'can_review' => $request->user()?->canReviewExpenseGroup($this->resource) ?? false,
        ];
    }
}
