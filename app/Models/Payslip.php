<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'company_id',
        'employee_id',
        'employee_code',
        'employee_name',
        'designation',
        'department_name',
        'location',
        'joining_date',
        'payable_days',
        'lop_days',
        'earnings',
        'deductions',
        'total_earnings',
        'total_deductions',
        'net_pay',
        'expense_reimbursements',
        'bank_name',
        'bank_account_number',
        'pan_number',
        'uan',
        'pf_number',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'payable_days' => 'decimal:1',
            'lop_days' => 'decimal:1',
            'earnings' => 'array',
            'deductions' => 'array',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'expense_reimbursements' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function totalPayable(): float
    {
        return (float) $this->net_pay;
    }

    public function periodLabel(): string
    {
        return $this->payrollPeriod?->label() ?? '—';
    }
}
