<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseGroup extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'name',
        'description',
        'from_date',
        'to_date',
        'travel_advance_amount',
        'status',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'travel_advance_amount' => 'decimal:2',
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

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function totalAmount(): float
    {
        return (float) $this->expenses->sum('amount');
    }

    public function approvedReimbursableAmount(): float
    {
        return (float) $this->expenses
            ->where('claim_reimbursement', true)
            ->where('status', Expense::STATUS_APPROVED)
            ->sum('amount');
    }

    public function netAdjustment(): float
    {
        return round($this->approvedReimbursableAmount() - (float) $this->travel_advance_amount, 2);
    }
}
