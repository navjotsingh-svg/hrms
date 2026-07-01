<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWfhRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApplyWfh() ?? false;
    }

    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'proofs' => ['nullable', 'array', 'max:10'],
            'proofs.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
