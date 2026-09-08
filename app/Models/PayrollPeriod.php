<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    public const TYPE_REGULAR = 'regular';

    public const TYPE_OFFBOARD = 'offboard';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'company_id',
        'year',
        'month',
        'type',
        'employee_id',
        'exit_case_id',
        'status',
        'processed_by_user_id',
        'processed_at',
        'paid_by_user_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function exitCase(): BelongsTo
    {
        return $this->belongsTo(ExitCase::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid',
            default => 'Processed',
        };
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function label(): string
    {
        $date = \Carbon\Carbon::create($this->year, $this->month, 1);

        if ($this->type === self::TYPE_OFFBOARD) {
            $employeeName = $this->relationLoaded('employee')
                ? $this->employee?->full_name
                : null;

            return $date->format('M Y').' · Offboard'.($employeeName ? ' · '.$employeeName : '');
        }

        return $date->format('M Y');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_OFFBOARD => 'Offboard',
            default => 'Regular',
        };
    }
}
