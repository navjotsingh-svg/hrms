<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalKeyResult extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'goal_id',
        'title',
        'description',
        'target_value',
        'current_value',
        'unit',
        'weight',
        'status',
        'due_date',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'float',
            'current_value' => 'float',
            'weight' => 'float',
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
