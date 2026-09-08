<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'used' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'adjusted' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
        ];
    }
}
