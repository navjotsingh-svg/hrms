<?php



namespace App\Services;



use App\Models\Company;

use App\Models\EmployeeSalary;

use Illuminate\Support\Facades\DB;



class CompanyPayrollSettingsService

{

    /** @return array<string, bool|float> */

    public function getForCompany(int $companyId): array

    {

        $company = Company::query()->findOrFail($companyId);



        return $this->format($company);

    }



    /** @param  array<string, bool|float|int|string|null>  $settings */

    public function updateForCompany(int $companyId, array $settings): array

    {

        $company = Company::query()->findOrFail($companyId);



        $payload = $this->normalizePayload($settings);



        DB::transaction(function () use ($company, $payload) {

            $company->update($payload);



            EmployeeSalary::query()

                ->where('company_id', $company->id)

                ->each(function (EmployeeSalary $salary) use ($payload) {

                    $amounts = $this->recalculateSalaryAmounts($salary, $payload);



                    if ($amounts !== []) {

                        $salary->update($amounts);

                    } else {

                        $salary->update([

                            'hra_percent' => $payload['hra_percent'],

                            'special_allowance_percent' => $payload['special_allowance_percent'],

                            'conveyance_allowance' => $payload['conveyance_allowance'],

                            'medical_allowance' => $payload['medical_allowance'],

                            'other_allowance' => $payload['other_allowance'],

                            'pf_applicable' => $payload['pf_applicable'],

                            'esi_applicable' => $payload['esi_applicable'],

                            'professional_tax_applicable' => $payload['professional_tax_applicable'],

                        ]);

                    }

                });

        });



        return $this->format($company->fresh());

    }



    /** @return array<string, bool|float> */

    public function format(Company $company): array

    {

        return [

            'pf_applicable' => (bool) $company->pf_applicable,

            'esi_applicable' => (bool) $company->esi_applicable,

            'professional_tax_applicable' => (bool) $company->professional_tax_applicable,

            'basic_salary_percent' => (float) ($company->basic_salary_percent ?? 50),

            'hra_percent' => (float) ($company->hra_percent ?? 40),

            'special_allowance_percent' => (float) ($company->special_allowance_percent ?? 0),

            'conveyance_allowance' => (float) ($company->conveyance_allowance ?? 0),

            'medical_allowance' => (float) ($company->medical_allowance ?? 0),

            'other_allowance' => (float) ($company->other_allowance ?? 0),

        ];

    }



    /** @param  array<string, bool|float|int|string|null>  $settings

     * @return array<string, bool|float>

     */

    public function normalizePayload(array $settings): array

    {

        return [

            'pf_applicable' => (bool) ($settings['pf_applicable'] ?? true),

            'esi_applicable' => (bool) ($settings['esi_applicable'] ?? false),

            'professional_tax_applicable' => (bool) ($settings['professional_tax_applicable'] ?? true),

            'basic_salary_percent' => round((float) ($settings['basic_salary_percent'] ?? 50), 2),

            'hra_percent' => round((float) ($settings['hra_percent'] ?? 40), 2),

            'special_allowance_percent' => round((float) ($settings['special_allowance_percent'] ?? 0), 2),

            'conveyance_allowance' => round((float) ($settings['conveyance_allowance'] ?? 0), 2),

            'medical_allowance' => round((float) ($settings['medical_allowance'] ?? 0), 2),

            'other_allowance' => round((float) ($settings['other_allowance'] ?? 0), 2),

        ];

    }



    /** @param  array<string, bool|float>  $settings

     * @return array<string, bool|float>

     */

    public function recalculateSalaryAmounts(EmployeeSalary $salary, array $settings): array

    {

        $monthlyCtc = (float) $salary->annual_ctc / 12;



        if ($monthlyCtc <= 0) {

            return [];

        }



        $basicPercent = (float) ($settings['basic_salary_percent'] ?? 50);

        $hraPercent = (float) ($settings['hra_percent'] ?? 40);

        $specialPercent = (float) ($settings['special_allowance_percent'] ?? 0);



        return [

            'basic_salary' => round($monthlyCtc * $basicPercent / 100, 2),

            'hra_percent' => $hraPercent,

            'special_allowance_percent' => $specialPercent,

            'hra' => round($monthlyCtc * $hraPercent / 100, 2),

            'special_allowance' => round($monthlyCtc * $specialPercent / 100, 2),

            'conveyance_allowance' => (float) ($settings['conveyance_allowance'] ?? 0),

            'medical_allowance' => (float) ($settings['medical_allowance'] ?? 0),

            'other_allowance' => (float) ($settings['other_allowance'] ?? 0),

            'pf_applicable' => (bool) ($settings['pf_applicable'] ?? true),

            'esi_applicable' => (bool) ($settings['esi_applicable'] ?? false),

            'professional_tax_applicable' => (bool) ($settings['professional_tax_applicable'] ?? true),

        ];

    }



    /** @return array<string, float> */

    public function computeAmountsForAnnualCtc(float $annualCtc, array $settings): array

    {

        $monthlyCtc = $annualCtc > 0 ? $annualCtc / 12 : 0;

        $basicPercent = (float) ($settings['basic_salary_percent'] ?? 50);

        $hraPercent = (float) ($settings['hra_percent'] ?? 40);

        $specialPercent = (float) ($settings['special_allowance_percent'] ?? 0);



        return [

            'basic_salary' => round($monthlyCtc * $basicPercent / 100, 2),

            'hra' => round($monthlyCtc * $hraPercent / 100, 2),

            'special_allowance' => round($monthlyCtc * $specialPercent / 100, 2),

            'conveyance_allowance' => (float) ($settings['conveyance_allowance'] ?? 0),

            'medical_allowance' => (float) ($settings['medical_allowance'] ?? 0),

            'other_allowance' => (float) ($settings['other_allowance'] ?? 0),

            'monthly_gross' => round(

                ($monthlyCtc * $basicPercent / 100)

                + ($monthlyCtc * $hraPercent / 100)

                + ($monthlyCtc * $specialPercent / 100)

                + (float) ($settings['conveyance_allowance'] ?? 0)

                + (float) ($settings['medical_allowance'] ?? 0)

                + (float) ($settings['other_allowance'] ?? 0),

                2

            ),

        ];

    }

}

