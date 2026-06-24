<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['public', 'company', 'optional', 'other'])],
            'frequency' => ['required', Rule::in(['fixed', 'variable'])],
            'duration' => ['required', Rule::in(['single', 'range'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->input('frequency') === 'fixed') {
            $rules['start_month'] = ['required', 'integer', 'min:1', 'max:12'];
            $rules['start_day'] = ['required', 'integer', 'min:1', 'max:31'];

            if ($this->input('duration') === 'range') {
                $rules['end_month'] = ['required', 'integer', 'min:1', 'max:12'];
                $rules['end_day'] = ['required', 'integer', 'min:1', 'max:31'];
            }
        } elseif ($this->input('duration') === 'single') {
            $rules['holiday_date'] = ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'];
        } else {
            $rules['from_date'] = ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/'];
            $rules['to_date'] = ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}$/', 'after_or_equal:from_date'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'holiday_date.regex' => 'Please select a valid date with a 4-digit year.',
            'from_date.regex' => 'Please select a valid from date with a 4-digit year.',
            'to_date.regex' => 'Please select a valid to date with a 4-digit year.',
        ];
    }
}
