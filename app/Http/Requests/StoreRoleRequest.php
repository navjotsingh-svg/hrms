<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'permission_slugs' => ['nullable', 'array'],
            'permission_slugs.*' => ['string', 'max:80'],
        ];
    }
}
