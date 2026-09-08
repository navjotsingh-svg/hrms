<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiDocumentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageDocuments();
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(array_keys(config('document_letters.categories', [])))],
            'prompt' => ['required', 'string', 'min:10', 'max:5000'],
            'employee_id' => ['nullable', 'integer'],
        ];
    }
}
