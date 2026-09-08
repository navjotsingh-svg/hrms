<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesEmployeeFields;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use ValidatesEmployeeFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');
        $grantPortalAccess = $this->boolean('give_portal_access');

        return array_merge(
            $this->employeeRules(
                $this->user()?->company_id,
                $employee?->id,
                $employee?->user_id,
                (bool) $employee?->user_id || $grantPortalAccess
            ),
            $this->employeeSalaryRulesForRequest(),
            [
                'salary_revision_notes' => ['nullable', 'string', 'max:500'],
            ],
        );
    }

    public function messages(): array
    {
        return $this->employeeMessages();
    }
}
