<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkEmployeeAbsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasFullAccess() || $user?->canManageAttendanceMasters();
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
