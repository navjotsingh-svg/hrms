<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceFeedbackFormQuestion extends Model
{
    protected $fillable = [
        'feedback_form_id',
        'question_bank_id',
        'question',
        'question_type',
        'weight',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(PerformanceFeedbackForm::class, 'feedback_form_id');
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(PerformanceQuestionBank::class, 'question_bank_id');
    }
}
