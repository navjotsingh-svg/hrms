<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipKeyResult extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_MISSED = 'missed';

    protected $fillable = [
        'pip_plan_id',
        'title',
        'description',
        'target_date',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function pipPlan(): BelongsTo
    {
        return $this->belongsTo(PipPlan::class);
    }
}
