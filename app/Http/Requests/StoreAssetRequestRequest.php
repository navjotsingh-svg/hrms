<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApplyAssets() ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_type_ids' => ['required', 'array', 'min:1', 'max:25'],
            'asset_type_ids.*' => ['integer', 'distinct', 'exists:asset_types,id'],
            'asset_type_id' => ['nullable', 'integer', 'exists:asset_types,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('asset_type_id') && ! $this->filled('asset_type_ids')) {
            $this->merge([
                'asset_type_ids' => [(int) $this->input('asset_type_id')],
            ]);
        }
    }
}
