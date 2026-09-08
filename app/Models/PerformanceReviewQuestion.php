<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReviewQuestion extends Model
{
    protected $fillable = [
        'cycle_id',
        'question',
        'weight',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'cycle_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PerformanceReviewAnswer::class, 'question_id');
    }
}
