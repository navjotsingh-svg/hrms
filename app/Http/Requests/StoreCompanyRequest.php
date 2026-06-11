<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCompanyFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    use ValidatesCompanyFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCompanyFields();
    }

    public function rules(): array
    {
        return $this->companyFieldRules();
    }

    public function messages(): array
    {
        return $this->companyMessages();
    }
}
