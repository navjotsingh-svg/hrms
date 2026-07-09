<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiHiringGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageHiring();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:200'],
            'requirements' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
