<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceCalibrationEntry extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'session_id',
        'employee_id',
        'review_id',
        'original_rating',
        'calibrated_rating',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_rating' => 'float',
            'calibrated_rating' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PerformanceCalibrationSession::class, 'session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }
}
