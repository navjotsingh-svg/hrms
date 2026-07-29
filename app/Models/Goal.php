<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    public const LEVEL_COMPANY = 'company';

    public const LEVEL_DEPARTMENT = 'department';

    public const LEVEL_INDIVIDUAL = 'individual';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_TEAM = 'team';

    public const VISIBILITY_COMPANY = 'company';

    protected $fillable = [
        'company_id',
        'level',
        'employee_id',
        'department_id',
        'parent_goal_id',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'parent_goal_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Goal::class, 'parent_goal_id');
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
        $children = $this->children()->get();

        if ($children->isNotEmpty()) {
            $progress = round((float) $children->avg('progress'), 2);
            $this->update(['progress' => min(100, $progress)]);

            return;
        }

        $results = $this->keyResults()->with('kpi')->get();

        if ($results->isEmpty()) {
            $this->update(['progress' => 0]);

            return;
        }

        foreach ($results as $keyResult) {
            $keyResult->syncFromLinkedKpi();
        }

        $results = $this->keyResults()->with('kpi')->get();
        $totalWeight = $results->sum('weight') ?: 1;
        $progress = $results->sum(function (GoalKeyResult $kr) use ($totalWeight) {
            return $kr->progressPercent() * ($kr->weight / $totalWeight);
        });

        $this->update(['progress' => round(min(100, $progress), 2)]);
    }
}
