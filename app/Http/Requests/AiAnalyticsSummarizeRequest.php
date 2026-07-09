<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiAnalyticsSummarizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->company_id;
    }

    public function rules(): array
    {
        return [
            'report_key' => ['required', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
        ];
    }
}
