<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiRoleAdviseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCompanyAdmin();
    }

    public function rules(): array
    {
        return [
            'role_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
