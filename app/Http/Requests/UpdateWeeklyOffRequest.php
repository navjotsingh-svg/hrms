<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weekdays' => ['required', 'array'],
            'weekdays.*' => ['integer', 'min:0', 'max:6'],
        ];
    }
}
