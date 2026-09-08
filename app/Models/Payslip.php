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
        'income_tax_applicable',
        'tax_regime',
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
            'income_tax_applicable' => 'boolean',
        ];
    }

    public function taxRegimeLabel(): ?string
    {
        if ($this->tax_regime === 'old') {
            return 'Old Regime';
        }

        if ($this->tax_regime === 'new') {
            return 'New Regime';
        }

        $tds = $this->incomeTaxDeduction();
        if (! $tds) {
            return null;
        }

        $label = (string) ($tds['label'] ?? '');

        if (str_contains($label, 'Old Regime')) {
            return 'Old Regime';
        }

        if (str_contains($label, 'New Regime')) {
            return 'New Regime';
        }

        return 'New Regime';
    }

    public function hasIncomeTax(): bool
    {
        return (bool) $this->income_tax_applicable || $this->incomeTaxDeduction() !== null;
    }

    public function incomeTaxDeduction(): ?array
    {
        $match = collect($this->deductions ?? [])
            ->first(fn (array $row) => str_starts_with((string) ($row['label'] ?? ''), 'Income Tax (TDS'));

        return $match ?: null;
    }

    public function incomeTaxAmount(): float
    {
        return (float) ($this->incomeTaxDeduction()['amount'] ?? 0);
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
