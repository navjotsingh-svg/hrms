<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceNetworkSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_allowed_ips' => ['nullable', 'array'],
            'attendance_allowed_ips.*' => ['string', 'max:45'],
        ];
    }
}
