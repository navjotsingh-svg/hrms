<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationRecommendation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'company_id',
        'employee_id',
        'review_cycle_id',
        'band_id',
        'current_salary',
        'recommended_increase_percent',
        'recommended_increase_amount',
        'recommended_new_salary',
        'merit_rating',
        'notes',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'current_salary' => 'decimal:2',
            'recommended_increase_percent' => 'decimal:2',
            'recommended_increase_amount' => 'decimal:2',
            'recommended_new_salary' => 'decimal:2',
            'merit_rating' => 'decimal:2',
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

    public function reviewCycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(CompensationBand::class, 'band_id');
    }
}
