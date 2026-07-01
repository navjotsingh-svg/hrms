<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResignationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApplyOffboarding() ?? false;
    }

    public function rules(): array
    {
        return [
            'proposed_last_working_date' => ['required', 'date', 'after_or_equal:today'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
