<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiHelpdeskSuggestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canApplyHelpdesk();
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
