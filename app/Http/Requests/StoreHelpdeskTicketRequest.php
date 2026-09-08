<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApplyHelpdesk() ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'helpdesk_category_id' => [
                'required',
                'integer',
                Rule::exists('helpdesk_categories', 'id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('status', 'active')),
            ],
            'priority' => ['nullable', Rule::in(array_keys(config('helpdesk.priorities', [])))],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
