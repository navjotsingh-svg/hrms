<?php

namespace App\Http\Requests;

use App\Models\LeaveRequestDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'session' => ['nullable', Rule::in([
                LeaveRequestDay::SESSION_FULL,
                LeaveRequestDay::SESSION_FIRST_HALF,
                LeaveRequestDay::SESSION_SECOND_HALF,
                LeaveRequestDay::SESSION_HOURLY,
            ])],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480', 'required_if:session,hourly'],
            'reason' => ['required', 'string', 'max:2000'],
            'proofs' => ['nullable', 'array', 'max:10'],
            'proofs.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
