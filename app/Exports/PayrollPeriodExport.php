<?php

namespace App\Exports;

use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayrollPeriodExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private Collection $payslips;

    /** @var string[] */
    private array $earningLabels;

    /** @var string[] */
    private array $deductionLabels;

    public function __construct(private PayrollPeriod $period, Collection $payslips)
    {
        $this->payslips = $payslips;
        $this->earningLabels = $this->collectLabels($payslips, 'earnings');
        $this->deductionLabels = $this->collectLabels($payslips, 'deductions');
    }

    public function title(): string
    {
        return 'Payroll '.$this->period->label();
    }

    public function headings(): array
    {
        return array_merge(
            ['Employee Code', 'Full Name', 'Designation', 'Department', 'Joining Date', 'Payable Days', 'LOP Days', 'Paid Days'],
            $this->earningLabels,
            ['Gross Salary'],
            $this->deductionLabels,
            ['Total Deductions', 'Net Salary', 'Bank Name', 'Account Number', 'PAN', 'UAN'],
        );
    }

    public function array(): array
    {
        return $this->payslips->map(function (Payslip $payslip) {
            $earnings = collect($payslip->earnings ?? [])->keyBy('label');
            $deductions = collect($payslip->deductions ?? [])->keyBy('label');

            $row = [
                $payslip->employee_code,
                $payslip->employee_name,
                $payslip->designation,
                $payslip->department_name,
                $payslip->joining_date?->format('d-m-Y'),
                (float) $payslip->payable_days,
                (float) $payslip->lop_days,
                round(max((float) $payslip->payable_days - (float) $payslip->lop_days, 0), 1),
            ];

            foreach ($this->earningLabels as $label) {
                $row[] = round((float) ($earnings[$label]['amount'] ?? 0), 2);
            }

            $row[] = (float) $payslip->total_earnings;

            foreach ($this->deductionLabels as $label) {
                $row[] = round((float) ($deductions[$label]['amount'] ?? 0), 2);
            }

            return array_merge($row, [
                (float) $payslip->total_deductions,
                (float) $payslip->net_pay,
                $payslip->bank_name,
                $payslip->bank_account_number,
                $payslip->pan_number,
                $payslip->uan,
            ]);
        })->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Component labels differ per employee (e.g. some have no HRA), so the
     * column set is the union of labels across all payslips in the period.
     */
    private function collectLabels(Collection $payslips, string $key): array
    {
        return $payslips
            ->flatMap(fn (Payslip $payslip) => collect($payslip->{$key} ?? [])->pluck('label'))
            ->filter(fn ($label) => filled($label) && $label !== 'Expense Reimbursement')
            ->unique()
            ->values()
            ->all();
    }
}
