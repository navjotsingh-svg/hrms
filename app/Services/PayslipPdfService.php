<?php

namespace App\Services;

use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;

class PayslipPdfService
{
    public function render(Payslip $payslip): \Barryvdh\DomPDF\PDF
    {
        $payslip->loadMissing(['payrollPeriod', 'company']);

        $company = $payslip->company;
        $logoPath = null;

        if ($company?->logo) {
            $candidate = public_path(ltrim($company->logo, '/'));

            if (is_file($candidate)) {
                $logoPath = $candidate;
            }
        }

        return Pdf::loadView('payroll.payslip-pdf', [
            'payslip' => $payslip,
            'company' => $company,
            'logoPath' => $logoPath,
            'periodLabel' => $payslip->payrollPeriod?->label(),
            'monthTitle' => $this->monthTitle($payslip),
        ])->setPaper('a4', 'portrait');
    }

    public function inline(Payslip $payslip): Response
    {
        $filename = $this->filename($payslip);

        return $this->render($payslip)->stream($filename);
    }

    public function download(Payslip $payslip): Response
    {
        $filename = $this->filename($payslip);

        return $this->render($payslip)->download($filename);
    }

    private function filename(Payslip $payslip): string
    {
        $period = str_replace(' ', '-', $payslip->periodLabel());
        $code = $payslip->employee_code ?: $payslip->employee_id;

        return "payslip-{$code}-{$period}.pdf";
    }

    private function monthTitle(Payslip $payslip): string
    {
        $period = $payslip->payrollPeriod;

        if (! $period) {
            return '—';
        }

        return Carbon::create($period->year, $period->month, 1)->format('M, Y');
    }
}
