<?php

namespace App\Http\Requests\Concerns;

use App\Models\Role;
use Illuminate\Validation\Rule;

trait ValidatesEmployeeFields
{
    use ValidatesEmployeeSalaryFields;

    protected function employeeRules(
        ?int $companyId = null,
        ?int $employeeId = null,
        ?int $userId = null,
        bool $checkUserEmailUnique = true,
    ): array {
        $emailRules = [
            'required',
            'email',
            'max:255',
            Rule::unique('employees', 'email')
                ->where(fn ($query) => $query->where('company_id', $companyId))
                ->ignore($employeeId),
        ];

        if ($checkUserEmailUnique) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($userId);
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => $emailRules,
            'personal_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'personal_email')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($employeeId),
            ],
            'phone' => [
                'required',
                'digits:10',
                Rule::unique('employees', 'phone')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($employeeId),
            ],
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($employeeId),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => [
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query) use ($companyId) {
                    $query->where('scope', 'company')
                        ->whereNotIn('slug', [Role::SLUG_SUPER_ADMIN, Role::SLUG_COMPANY_ADMIN])
                        ->where(function ($builder) use ($companyId) {
                            $builder
                                ->whereNull('company_id')
                                ->orWhere('company_id', $companyId);
                        });
                }),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'weekly_off_mode' => ['required', Rule::in(['company_default', 'custom'])],
            'weekly_off_weekdays' => [
                Rule::excludeIf(fn () => ($this->input('weekly_off_mode') ?? 'company_default') !== 'custom'),
                'required',
                'array',
                'min:1',
            ],
            'weekly_off_weekdays.*' => ['integer', 'min:0', 'max:6'],
            'leave_type_ids' => ['required', 'array', 'min:1'],
            'leave_type_ids.*' => [
                'integer',
                Rule::exists('leave_types', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('status', 'active')),
            ],
            'designation' => ['nullable', 'string', 'max:100'],
            'joining_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => [
                'required',
                'date',
                'before_or_equal:'.now()->subYears(18)->format('Y-m-d'),
                'after:'.now()->subYears(100)->format('Y-m-d'),
            ],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'probation_applicable' => ['sometimes', 'boolean'],
            'probation_period_months' => ['nullable', 'required_if:probation_applicable,true', 'integer', 'min:1', 'max:24'],
            'probation_end_date' => ['nullable', 'required_if:probation_applicable,true', 'date', 'after_or_equal:joining_date'],
            'probation_status' => ['nullable', Rule::in(['on_probation', 'confirmed', 'extended', 'not_applicable'])],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'give_portal_access' => ['sometimes', 'boolean'],
        ];
    }

    protected function employeeMessages(): array
    {
        return [
            'phone.digits' => 'Mobile number must be exactly 10 digits.',
        ];
    }
}
