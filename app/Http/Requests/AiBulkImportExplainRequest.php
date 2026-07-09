<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiBulkImportExplainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canManageEmployees();
    }

    public function rules(): array
    {
        return [];
    }
}
