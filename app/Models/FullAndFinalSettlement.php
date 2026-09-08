<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FullAndFinalSettlement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'exit_case_id',
        'payroll_period_id',
        'employee_id',
        'leave_encashment',
        'pending_dues',
        'deductions',
        'net_payable',
        'settlement_notes',
        'status',
        'processed_by_user_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'leave_encashment' => 'decimal:2',
            'pending_dues' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function exitCase(): BelongsTo
    {
        return $this->belongsTo(ExitCase::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
