<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApplyExpenses() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'travel_advance_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
