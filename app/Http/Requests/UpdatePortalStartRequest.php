<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortalStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_portal_start_date' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_portal_start_date.regex' => 'Portal start date must use a valid 4-digit year (YYYY-MM-DD).',
        ];
    }
}
