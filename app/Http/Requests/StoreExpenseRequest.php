<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApplyExpenses() ?? false;
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'merchant' => ['nullable', 'string', 'max:255'],
            'expense_type_id' => ['required', 'integer', 'exists:expense_types,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'claim_reimbursement' => ['nullable', 'boolean'],
            'submit' => ['nullable', 'boolean'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,webp,zip,xlsx,xls'],
        ];
    }
}
