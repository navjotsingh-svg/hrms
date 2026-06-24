<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceQuestionBank extends Model
{
    public const TYPE_RATING = 'rating';

    public const TYPE_TEXT = 'text';

    protected $table = 'performance_question_bank';

    protected $fillable = [
        'company_id',
        'category',
        'question',
        'question_type',
        'default_weight',
        'sort_order',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'default_weight' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
