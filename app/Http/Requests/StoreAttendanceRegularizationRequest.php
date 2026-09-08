<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'attendance_date' => ['required_without_all:dates,entries', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'dates' => ['required_without_all:attendance_date,entries', 'array', 'min:1', 'max:31'],
            'dates.*' => ['date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'entries' => ['required_without_all:attendance_date,dates', 'array', 'min:1', 'max:31'],
            'entries.*.date' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'entries.*.punch_in_time' => ['nullable', 'date_format:H:i'],
            'entries.*.punch_out_time' => ['nullable', 'date_format:H:i'],
            'punch_in_time' => ['nullable', 'date_format:H:i'],
            'punch_out_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $entries = $this->input('entries');

            if (! is_array($entries)) {
                return;
            }

            foreach ($entries as $index => $entry) {
                $punchIn = $entry['punch_in_time'] ?? null;
                $punchOut = $entry['punch_out_time'] ?? null;

                if (! $punchIn && ! $punchOut) {
                    $validator->errors()->add(
                        "entries.{$index}.punch_in_time",
                        'Provide at least a punch in time for each selected day.',
                    );
                }
            }
        });
    }
}
