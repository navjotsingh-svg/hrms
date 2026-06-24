<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceReviewPair extends Model
{
    public const RELATIONSHIP_SELF = 'self';

    public const RELATIONSHIP_MANAGER = 'manager';

    public const RELATIONSHIP_PEER = 'peer';

    public const RELATIONSHIP_HR = 'hr';

    protected $fillable = [
        'cycle_id',
        'reviewee_employee_id',
        'reviewer_employee_id',
        'relationship',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'cycle_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewee_employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(PerformanceReview::class, 'pair_id');
    }
}
