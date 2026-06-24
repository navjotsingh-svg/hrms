<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_TEAM = 'team';

    public const VISIBILITY_COMPANY = 'company';

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'description',
        'period_start',
        'period_end',
        'status',
        'visibility',
        'progress',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'progress' => 'float',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(GoalKeyResult::class)->orderBy('sort_order');
    }

    public function recalculateProgress(): void
    {
        $results = $this->keyResults;

        if ($results->isEmpty()) {
            $this->update(['progress' => 0]);

            return;
        }

        $totalWeight = $results->sum('weight') ?: 1;
        $progress = $results->sum(function (GoalKeyResult $kr) use ($totalWeight) {
            $pct = $kr->target_value > 0
                ? min(100, ($kr->current_value / $kr->target_value) * 100)
                : 0;

            return $pct * ($kr->weight / $totalWeight);
        });

        $this->update(['progress' => round($progress, 2)]);
    }
}
