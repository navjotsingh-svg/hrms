<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiGenericPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->company_id;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:5000'],
            'employee_name' => ['nullable', 'string', 'max:200'],
        ];
    }
}
