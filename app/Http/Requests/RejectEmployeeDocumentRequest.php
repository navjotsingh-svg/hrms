<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
