<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePersonalSection extends Model
{
    public const SECTION_TYPES = ['address', 'emergency_contact'];

    public const SECTION_LABELS = [
        'address' => 'Address',
        'emergency_contact' => 'Emergency Contact',
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'section_type',
        'payload',
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
            'payload' => 'array',
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

    public function label(): string
    {
        return self::SECTION_LABELS[$this->section_type] ?? $this->section_type;
    }
}
