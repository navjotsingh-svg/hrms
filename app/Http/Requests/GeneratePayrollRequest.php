<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:'.now()->year],
            'month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $year = (int) $this->input('year');

                    if ($year === (int) now()->year && (int) $value > (int) now()->month) {
                        $fail('Payroll cannot be generated for a future month.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'year.max' => 'Payroll cannot be generated for a future year.',
        ];
    }
}
