<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePaymentMethod extends Model
{
    public const PAYMENT_MODES = ['bank_transfer', 'cash', 'cheque'];

    protected $fillable = [
        'company_id',
        'employee_id',
        'payment_mode',
        'bank_name',
        'bank_branch',
        'bank_address',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'status',
        'notes',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'submitted_at',
        'reviewed_at',
    ];

    public function proofs()
    {
        return $this->hasMany(EmployeePaymentMethodProof::class);
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function canBeResubmitted(): bool
    {
        return in_array($this->status, ['rejected', 'approved'], true);
    }

    public function isLocked(): bool
    {
        return $this->status === 'pending';
    }

    public function requiresBankDetails(): bool
    {
        return $this->payment_mode === 'bank_transfer';
    }
}
