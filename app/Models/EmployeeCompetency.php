<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompetency extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'competency_id',
        'current_level',
        'target_level',
        'notes',
        'assessed_at',
        'assessed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'current_level' => 'integer',
            'target_level' => 'integer',
            'assessed_at' => 'date',
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

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
