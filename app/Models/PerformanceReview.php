<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReview extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'cycle_id',
        'pair_id',
        'reviewee_employee_id',
        'reviewer_employee_id',
        'reviewer_user_id',
        'status',
        'overall_rating',
        'summary_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'float',
            'submitted_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'cycle_id');
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewPair::class, 'pair_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewee_employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PerformanceReviewAnswer::class, 'review_id');
    }
}
