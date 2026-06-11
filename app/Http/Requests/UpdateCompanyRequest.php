<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCompanyFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
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
        return $this->companyFieldRules(
            $this->company?->id,
            $this->company?->adminUser?->id
        );
    }

    public function messages(): array
    {
        return $this->companyMessages();
    }
}
