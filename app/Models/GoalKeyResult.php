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
        'performance_kpi_id',
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

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(PerformanceKpi::class, 'performance_kpi_id');
    }

    public function syncFromLinkedKpi(): bool
    {
        if (! $this->performance_kpi_id) {
            return false;
        }

        $kpi = $this->kpi()->first();

        if (! $kpi) {
            return false;
        }

        $updates = [
            'current_value' => $kpi->current_value,
            'target_value' => $kpi->target_value,
            'unit' => $kpi->unit,
        ];

        if ($kpi->status === PerformanceKpi::STATUS_COMPLETED) {
            $updates['status'] = self::STATUS_COMPLETED;
            $updates['current_value'] = $kpi->target_value;
        } elseif ($kpi->progressPercent() > 0) {
            $updates['status'] = self::STATUS_IN_PROGRESS;
        }

        $this->fill($updates);

        if (! $this->isDirty()) {
            return false;
        }

        $this->save();

        return true;
    }

    public function progressPercent(): float
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 100.0;
        }

        if ($this->performance_kpi_id) {
            $kpi = $this->relationLoaded('kpi') ? $this->kpi : $this->kpi()->first();

            if ($kpi) {
                return $kpi->progressPercent();
            }
        }

        if ((float) $this->target_value <= 0) {
            return 0;
        }

        return min(100, round(((float) $this->current_value / (float) $this->target_value) * 100, 2));
    }
}
