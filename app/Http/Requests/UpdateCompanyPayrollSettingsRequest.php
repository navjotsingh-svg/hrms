<?php



namespace App\Http\Requests;



use Illuminate\Foundation\Http\FormRequest;



class UpdateCompanyPayrollSettingsRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    public function rules(): array

    {

        return [

            'pf_applicable' => ['required', 'boolean'],

            'esi_applicable' => ['required', 'boolean'],

            'professional_tax_applicable' => ['required', 'boolean'],

            'basic_salary_percent' => ['required', 'numeric', 'min:1', 'max:100'],

            'hra_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'special_allowance_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'conveyance_allowance' => ['required', 'numeric', 'min:0'],

            'medical_allowance' => ['required', 'numeric', 'min:0'],

            'other_allowance' => ['required', 'numeric', 'min:0'],

        ];

    }

}

