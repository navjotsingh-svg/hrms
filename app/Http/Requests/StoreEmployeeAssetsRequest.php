<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'assets' => ['required', 'array'],
            'assets.*.asset_type_id' => [
                'required',
                'integer',
                Rule::exists('asset_types', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')),
            ],
            'assets.*.is_assigned' => ['required', 'boolean'],
            'assets.*.description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
