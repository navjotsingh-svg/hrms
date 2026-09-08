<?php

namespace App\Http\Requests;

use App\Models\EmployeeComplianceField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeComplianceFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fieldType = $this->input('field_type');

        $valueRules = match ($fieldType) {
            'pan' => ['required', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'],
            'aadhaar' => ['required', 'string', 'size:12', 'regex:/^[0-9]{12}$/'],
            'uan' => ['required', 'string', 'size:12', 'regex:/^[0-9]{12}$/'],
            'pf' => ['required', 'string', 'max:30'],
            'esi' => ['required', 'string', 'max:30'],
            default => ['required', 'string', 'max:255'],
        };

        return [
            'field_type' => ['required', Rule::in(EmployeeComplianceField::FIELD_TYPES)],
            'value' => $valueRules,
        ];
    }
}
