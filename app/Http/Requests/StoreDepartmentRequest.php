<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('code') === '') {
            $this->merge(['code' => null]);
        }
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
