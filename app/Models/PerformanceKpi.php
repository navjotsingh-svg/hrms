<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceKpi extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'performance_kpis';

    protected $fillable = [
        'company_id',
        'employee_id',
        'title',
        'description',
        'target_value',
        'current_value',
        'unit',
        'frequency',
        'period_start',
        'period_end',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'float',
            'current_value' => 'float',
            'period_start' => 'date',
            'period_end' => 'date',
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

    public function progressPercent(): float
    {
        if ((float) $this->target_value <= 0) {
            return 0;
        }

        return min(100, round(((float) $this->current_value / (float) $this->target_value) * 100, 2));
    }
}
