<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeTaxRegimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_regime' => ['required', Rule::in([Employee::TAX_REGIME_OLD, Employee::TAX_REGIME_NEW])],
        ];
    }
}
