<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_period_id' => $this->payroll_period_id,
            'employee_id' => $this->employee_id,
            'employee_code' => $this->employee_code,
            'employee_name' => $this->employee_name,
            'designation' => $this->designation,
            'department_name' => $this->department_name,
            'location' => $this->location,
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'payable_days' => (float) $this->payable_days,
            'lop_days' => (float) $this->lop_days,
            'earnings' => $this->earnings ?? [],
            'deductions' => $this->deductions ?? [],
            'total_earnings' => (float) $this->total_earnings,
            'total_deductions' => (float) $this->total_deductions,
            'net_pay' => (float) $this->net_pay,
            'expense_reimbursements' => (float) $this->expense_reimbursements,
            'total_payable' => $this->totalPayable(),
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'pan_number' => $this->pan_number,
            'uan' => $this->uan,
            'pf_number' => $this->pf_number,
            'period' => new PayrollPeriodResource($this->whenLoaded('payrollPeriod')),
            'period_label' => $this->periodLabel(),
            'view_url' => url("/api/v1/payslips/{$this->id}/view"),
            'download_url' => url("/api/v1/payslips/{$this->id}/download"),
        ];
    }
}
