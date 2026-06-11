<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeComplianceField extends Model
{
    public const FIELD_TYPES = ['pan', 'aadhaar', 'uan', 'pf', 'esi'];

    public const EMPLOYEE_COLUMN_MAP = [
        'pan' => 'pan_number',
        'aadhaar' => 'aadhaar_number',
        'uan' => 'uan',
        'pf' => 'pf_number',
        'esi' => 'esi_number',
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'field_type',
        'value',
        'status',
        'notes',
        'submitted_by_user_id',
        'reviewed_by_user_id',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function canBeResubmitted(): bool
    {
        return in_array($this->status, ['rejected', 'approved'], true);
    }

    public function isLocked(): bool
    {
        return $this->status === 'pending';
    }

    public function employeeColumn(): ?string
    {
        return self::EMPLOYEE_COLUMN_MAP[$this->field_type] ?? null;
    }
}
