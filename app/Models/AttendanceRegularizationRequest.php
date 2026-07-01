<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRegularizationRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'employee_id',
        'batch_id',
        'supersedes_request_id',
        'attendance_date',
        'requested_punch_in',
        'requested_punch_out',
        'original_punch_in',
        'original_punch_out',
        'reason',
        'status',
        'applied_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'requested_punch_in' => 'datetime',
            'requested_punch_out' => 'datetime',
            'original_punch_in' => 'datetime',
            'original_punch_out' => 'datetime',
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

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function supersedesRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_request_id');
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class, 'regularization_request_id');
    }
}
