<?php







namespace App\Http\Requests\Concerns;







trait ValidatesEmployeeSalaryFields



{



    protected function employeeSalaryRules(): array
    {
        return [
            'annual_ctc' => ['required', 'numeric', 'min:1'],
            'salary_effective_from' => ['required', 'date'],
            'salary_payout_from' => ['nullable', 'date', 'after_or_equal:salary_effective_from'],
        ];
    }

    protected function optionalEmployeeSalaryRules(): array
    {
        return [
            'annual_ctc' => ['nullable', 'numeric', 'min:0'],
            'salary_effective_from' => ['nullable', 'date'],
            'salary_payout_from' => ['nullable', 'date', 'after_or_equal:salary_effective_from'],
        ];
    }

    protected function employeeSalaryRulesForRequest(): array
    {
        return $this->boolean('is_paid_employee', true)
            ? $this->employeeSalaryRules()
            : $this->optionalEmployeeSalaryRules();
    }







    protected function salaryFieldKeys(): array



    {



        return [



            'annual_ctc',



            'salary_effective_from',



            'salary_payout_from',



        ];



    }



}



