<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    use ValidatesEmployeeFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->employeeRules(
                $this->user()?->company_id,
                null,
                null,
                $this->boolean('give_portal_access')
            ),
            $this->employeeSalaryRulesForRequest()
        );
    }

    public function messages(): array
    {
        return $this->employeeMessages();
    }
}
