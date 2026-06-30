<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $leaveTypeId = $this->route('leave_type')?->id ?? $this->route('leave_type');

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('leave_types', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($leaveTypeId),
            ],
            'annual_quota' => ['nullable', 'numeric', 'min:0', Rule::when($this->boolean('is_hourly_leave'), 'max:8760', 'max:365')],
            'max_days_per_request' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'max_days_per_month' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'is_hourly_leave' => ['required', 'boolean'],
            'max_hours_per_month' => [
                'nullable',
                'integer',
                'min:1',
                'max:744',
                Rule::requiredIf(fn () => $this->boolean('is_hourly_leave')),
            ],
            'allowed_hourly_durations' => ['nullable', 'array', 'max:10'],
            'allowed_hourly_durations.*' => ['integer', 'min:15', 'max:480'],
            'is_paid' => ['required', 'boolean'],
            'allows_attendance_punch' => ['required', 'boolean'],
            'requires_proof' => ['required', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
