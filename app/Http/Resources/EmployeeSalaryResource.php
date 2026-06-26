<?php



namespace App\Http\Resources;



use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;



class EmployeeSalaryResource extends JsonResource

{

    public function toArray(Request $request): array

    {

        return [

            'id' => $this->id,

            'annual_ctc' => (float) $this->annual_ctc,

            'basic_salary' => (float) $this->basic_salary,

            'hra_percent' => (float) $this->hra_percent,

            'special_allowance_percent' => (float) $this->special_allowance_percent,

            'hra' => (float) $this->hra,

            'special_allowance' => (float) $this->special_allowance,

            'conveyance_allowance' => (float) $this->conveyance_allowance,

            'medical_allowance' => (float) $this->medical_allowance,

            'other_allowance' => (float) $this->other_allowance,

            'monthly_gross' => $this->monthly_gross,

            'pf_applicable' => $this->pf_applicable,

            'esi_applicable' => $this->esi_applicable,

            'professional_tax_applicable' => $this->professional_tax_applicable,

            'payment_mode' => $this->payment_mode,

            'bank_name' => $this->bank_name,

            'account_holder_name' => $this->account_holder_name,

            'account_number' => $this->account_number,

            'ifsc_code' => $this->ifsc_code,

            'salary_effective_from' => $this->salary_effective_from?->format('Y-m-d'),

            'salary_payout_from' => $this->salary_payout_from?->format('Y-m-d'),

        ];

    }

}

