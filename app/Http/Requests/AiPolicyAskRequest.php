<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiPolicyAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->company_id;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
