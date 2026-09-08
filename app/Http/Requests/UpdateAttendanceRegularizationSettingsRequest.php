<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRegularizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageAttendanceMasters() ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'previous_month_cutoff_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'max_requests_per_month' => ['nullable', 'integer', 'min:1', 'max:100'],
            'block_current_day_until_complete' => ['required', 'boolean'],
        ];
    }
}
