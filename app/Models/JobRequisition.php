<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobRequisition extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'department_id',
        'title',
        'description',
        'headcount',
        'employment_type',
        'budget_min',
        'budget_max',
        'urgency',
        'status',
        'requested_by_user_id',
        'approver_user_id',
        'approved_at',
        'rejection_reason',
        'job_id',
    ];

    protected function casts(): array
    {
        return [
            'headcount' => 'integer',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobPosting::class, 'requisition_id');
    }
}
