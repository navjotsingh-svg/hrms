<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentTypeRequest extends FormRequest
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
        $documentTypeId = $this->route('document_type')?->id ?? $this->route('document_type');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('document_types', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($documentTypeId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('document_types', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($documentTypeId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_required' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
