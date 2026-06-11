<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'document_type_id' => [
                'required',
                'integer',
                Rule::exists('document_types', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')),
            ],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'files' => ['nullable', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = $this->user()?->company_id;
            $documentType = DocumentType::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->find((int) $this->input('document_type_id'));

            if (! $documentType) {
                return;
            }

            if ($documentType->allow_multiple) {
                if (! $this->hasFile('files')) {
                    $validator->errors()->add('files', 'Please select at least one file to upload.');
                }

                return;
            }

            if (! $this->hasFile('file')) {
                $validator->errors()->add('file', 'Please select a file to upload.');
            }
        });
    }

    public function uploadedFiles(): array
    {
        $companyId = $this->user()?->company_id;
        $documentType = DocumentType::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->find((int) $this->input('document_type_id'));

        if ($documentType?->allow_multiple) {
            return array_values($this->file('files', []));
        }

        $file = $this->file('file');

        return $file ? [$file] : [];
    }
}
