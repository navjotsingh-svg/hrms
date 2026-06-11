<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canRegularizeAttendance() ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'attendance_date' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'punch_in_time' => ['nullable', 'date_format:H:i'],
            'punch_out_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
