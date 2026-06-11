<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $holidayId = $this->route('holiday')?->id ?? $this->route('holiday');

        return [
            'name' => ['required', 'string', 'max:150'],
            'date' => [
                'required',
                'date',
                Rule::unique('holidays', 'date')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($holidayId),
            ],
            'type' => ['required', Rule::in(['public', 'company', 'optional'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
