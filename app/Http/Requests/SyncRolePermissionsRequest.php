<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_slugs' => ['required', 'array'],
            'permission_slugs.*' => ['string', 'max:80'],
        ];
    }
}
