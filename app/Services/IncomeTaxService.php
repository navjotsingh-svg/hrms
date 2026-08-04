<?php

namespace App\Services;

use App\Models\Employee;

class IncomeTaxService
{
    public function monthlyTds(
        float $monthlyGross,
        float $annualCtc,
        float $monthlyPfEmployee,
        string $regime,
        float $attendanceFactor = 1.0,
    ): float {
        if ($monthlyGross <= 0) {
            return 0.0;
        }

        $regime = $this->normalizeRegime($regime);
        $annualGross = max($monthlyGross * 12, $annualCtc);
        $annualPf = round($monthlyPfEmployee * 12, 2);
        $taxableIncome = max(0, $annualGross - $annualPf - $this->standardDeduction($regime));
        $annualTax = $this->annualTaxOnTaxableIncome($taxableIncome, $regime);
        $monthlyTax = $annualTax / 12;

        if ($attendanceFactor < 1) {
            $monthlyTax *= $attendanceFactor;
        }

        return round(max(0, $monthlyTax), 2);
    }

    public function normalizeRegime(?string $regime): string
    {
        return $regime === Employee::TAX_REGIME_OLD
            ? Employee::TAX_REGIME_OLD
            : Employee::TAX_REGIME_NEW;
    }

    public function regimeLabel(?string $regime): string
    {
        return $this->normalizeRegime($regime) === Employee::TAX_REGIME_OLD ? 'Old' : 'New';
    }

    private function standardDeduction(string $regime): float
    {
        return (float) config("payroll.income_tax.{$regime}.standard_deduction", 0);
    }

    private function annualTaxOnTaxableIncome(float $taxableIncome, string $regime): float
    {
        if ($taxableIncome <= 0) {
            return 0.0;
        }

        $slabs = config("payroll.income_tax.{$regime}.slabs", []);
        $tax = 0.0;
        $previousLimit = 0.0;

        foreach ($slabs as $slab) {
            $limit = $slab['upto'] ?? null;
            $rate = (float) ($slab['rate'] ?? 0);

            if ($limit === null) {
                $taxableInSlab = max(0, $taxableIncome - $previousLimit);
            } else {
                $taxableInSlab = max(0, min($taxableIncome, (float) $limit) - $previousLimit);
            }

            $tax += $taxableInSlab * $rate;

            if ($limit === null || $taxableIncome <= (float) $limit) {
                break;
            }

            $previousLimit = (float) $limit;
        }

        $rebate = config("payroll.income_tax.{$regime}.rebate", []);
        $maxTaxable = (float) ($rebate['max_taxable_income'] ?? 0);

        if ($maxTaxable > 0 && $taxableIncome <= $maxTaxable) {
            if ($rebate['max_tax'] === null) {
                return 0.0;
            }

            $tax = max(0, $tax - (float) $rebate['max_tax']);
        }

        return max(0, round($tax, 2));
    }
}
