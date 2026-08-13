<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;



class UpdateCompanyPayrollSettingsRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    protected function prepareForValidation(): void

    {

        $booleanFields = [

            'pf_applicable',

            'esi_applicable',

            'professional_tax_applicable',

            'income_tax_applicable',

        ];



        foreach ($booleanFields as $field) {

            if ($this->exists($field)) {

                $this->merge([

                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),

                ]);

            }

        }

    }



    public function rules(): array

    {

        return [

            'pf_applicable' => ['required', 'boolean'],

            'esi_applicable' => ['required', 'boolean'],

            'professional_tax_applicable' => ['required', 'boolean'],

            'income_tax_applicable' => ['required', 'boolean'],

            'basic_salary_percent' => ['required', 'numeric', 'min:1', 'max:100'],

            'hra_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'special_allowance_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'conveyance_allowance' => ['required', 'numeric', 'min:0'],

            'medical_allowance' => ['required', 'numeric', 'min:0'],

            'other_allowance' => ['required', 'numeric', 'min:0'],

        ];

    }

}

