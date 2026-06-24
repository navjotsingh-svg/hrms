<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYOUT_UNPAID = 'unpaid';

    public const PAYOUT_PAID = 'paid';

    protected $fillable = [
        'company_id',
        'employee_id',
        'expense_group_id',
        'is_independent',
        'expense_date',
        'merchant',
        'expense_type_id',
        'amount',
        'description',
        'reference_number',
        'claim_reimbursement',
        'status',
        'payout_status',
        'payroll_period_id',
        'paid_at',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_independent' => 'boolean',
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'claim_reimbursement' => 'boolean',
            'paid_at' => 'datetime',
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

    public function expenseGroup(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class);
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }
}
