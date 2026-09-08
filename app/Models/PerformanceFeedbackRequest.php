<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceFeedbackRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'feedback_form_id',
        'subject_employee_id',
        'reviewer_employee_id',
        'requested_by_user_id',
        'reviewer_user_id',
        'status',
        'context_notes',
        'due_date',
        'overall_rating',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'overall_rating' => 'float',
            'submitted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function feedbackForm(): BelongsTo
    {
        return $this->belongsTo(PerformanceFeedbackForm::class, 'feedback_form_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'subject_employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PerformanceFeedbackRequestAnswer::class, 'request_id');
    }
}
