<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GrantCompOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'numeric', 'min:0.5', 'max:30'],
        ];
    }
}
