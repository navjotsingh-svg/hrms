<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewWfhRequestRequest extends FormRequest
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
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
