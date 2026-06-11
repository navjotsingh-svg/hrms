<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $assetTypeId = $this->route('asset_type')?->id ?? $this->route('asset_type');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_types', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($assetTypeId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
