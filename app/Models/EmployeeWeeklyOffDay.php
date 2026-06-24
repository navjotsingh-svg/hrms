<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWeeklyOffDay extends Model
{
    protected $fillable = [
        'employee_id',
        'weekday',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
