<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'year',
        'allocated',
        'used',
        'pending',
        'adjusted',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'allocated' => 'float',
            'used' => 'float',
            'pending' => 'float',
            'adjusted' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function available(): float
    {
        if ($this->leaveType?->isUnlimitedLeave()) {
            return PHP_FLOAT_MAX;
        }

        return max(0, ($this->allocated + $this->adjusted) - $this->used - $this->pending);
    }
}
