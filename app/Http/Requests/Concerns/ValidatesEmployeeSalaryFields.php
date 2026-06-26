<?php



namespace App\Http\Requests\Concerns;



trait ValidatesEmployeeSalaryFields

{

    protected function employeeSalaryRules(): array

    {

        return [

            'annual_ctc' => ['required', 'numeric', 'min:1'],

            'basic_salary' => ['required', 'numeric', 'min:1'],

            'hra_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'special_allowance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'conveyance_allowance' => ['nullable', 'numeric', 'min:0'],

            'medical_allowance' => ['nullable', 'numeric', 'min:0'],

            'other_allowance' => ['nullable', 'numeric', 'min:0'],

            'pf_applicable' => ['sometimes', 'boolean'],

            'esi_applicable' => ['sometimes', 'boolean'],

            'professional_tax_applicable' => ['sometimes', 'boolean'],

            'salary_effective_from' => ['required', 'date'],

            'salary_payout_from' => ['nullable', 'date', 'after_or_equal:salary_effective_from'],

        ];

    }



    protected function salaryFieldKeys(): array

    {

        return [

            'annual_ctc',

            'basic_salary',

            'hra_percent',

            'special_allowance_percent',

            'conveyance_allowance',

            'medical_allowance',

            'other_allowance',

            'pf_applicable',

            'esi_applicable',

            'professional_tax_applicable',

            'salary_effective_from',

            'salary_payout_from',

        ];

    }

}

