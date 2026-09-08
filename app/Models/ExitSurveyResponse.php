<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitSurveyResponse extends Model
{
    protected $fillable = [
        'exit_case_id',
        'employee_id',
        'responses',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function exitCase(): BelongsTo
    {
        return $this->belongsTo(ExitCase::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
