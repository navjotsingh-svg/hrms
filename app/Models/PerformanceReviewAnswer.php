<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReviewAnswer extends Model
{
    protected $fillable = [
        'review_id',
        'question_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewQuestion::class, 'question_id');
    }
}
