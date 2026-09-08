<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceFeedbackRequestAnswer extends Model
{
    protected $fillable = [
        'request_id',
        'form_question_id',
        'rating',
        'response_text',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PerformanceFeedbackRequest::class, 'request_id');
    }

    public function formQuestion(): BelongsTo
    {
        return $this->belongsTo(PerformanceFeedbackFormQuestion::class, 'form_question_id');
    }
}
