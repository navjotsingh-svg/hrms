<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'annual_quota',
        'max_days_per_request',
        'max_days_per_month',
        'is_hourly_leave',
        'max_hours_per_month',
        'allowed_hourly_durations',
        'is_paid',
        'allows_attendance_punch',
        'requires_proof',
        'color',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'annual_quota' => 'float',
            'max_days_per_request' => 'float',
            'max_days_per_month' => 'float',
            'is_hourly_leave' => 'boolean',
            'max_hours_per_month' => 'integer',
            'allowed_hourly_durations' => 'array',
            'is_paid' => 'boolean',
            'allows_attendance_punch' => 'boolean',
            'requires_proof' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isCompOff(): bool
    {
        return in_array(strtoupper($this->code), ['COMP', 'CO'], true);
    }

    public function allowsAttendancePunch(): bool
    {
        if ($this->allows_attendance_punch) {
            return true;
        }

        $code = strtoupper(trim((string) $this->code));

        if (in_array($code, ['WFH', 'WFHOME'], true)) {
            return true;
        }

        return str_contains(strtolower(trim((string) $this->name)), 'work from home');
    }

    public function isWorkFromHome(): bool
    {
        return $this->allowsAttendancePunch();
    }

    public function isHourlyLeave(): bool
    {
        return (bool) $this->is_hourly_leave;
    }

    public function usesHourQuota(): bool
    {
        return $this->isHourlyLeave();
    }

    public function quotaUnit(): string
    {
        return $this->usesHourQuota() ? 'hours' : 'days';
    }

    public function isUnlimitedLeave(): bool
    {
        return $this->annual_quota === null && ! $this->isCompOff();
    }

    public function applicationPolicyLabel(): string
    {
        if ($this->isHourlyLeave()) {
            $parts = [
                'Short leave type',
                'Options: '.collect($this->allowedHourlyDurations())
                    ->map(fn (int $minutes) => LeaveRequestDay::formatDurationLabel($minutes))
                    ->join(', '),
            ];

            if ($this->max_hours_per_month !== null) {
                $parts[] = 'Max '.$this->max_hours_per_month.' hour(s) per month';
            }

            if ($this->annual_quota !== null) {
                $parts[] = 'Annual quota: '.$this->formatLimitDays($this->annual_quota).' hour(s)';
            }

            if ($this->max_days_per_request !== null) {
                $parts[] = 'Max '.$this->formatLimitDays($this->max_days_per_request).' hour(s) per request';
            }

            return implode(' · ', $parts);
        }

        $parts = [];

        if ($this->max_days_per_request !== null) {
            $parts[] = 'Max '.$this->formatLimitDays($this->max_days_per_request).' per request';
        } else {
            $parts[] = 'Can apply full balance in one request';
        }

        if ($this->max_days_per_month !== null) {
            $parts[] = 'Max '.$this->formatLimitDays($this->max_days_per_month).' per month';
        }

        return implode(' · ', $parts);
    }

    public function allowedHourlyDurations(): array
    {
        $durations = collect($this->allowed_hourly_durations ?? [])
            ->map(fn ($minutes) => (int) $minutes)
            ->filter(fn (int $minutes) => $minutes > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $durations !== [] ? $durations : [60, 120];
    }

    private function formatLimitDays(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? (string) (int) $value : (string) $value;
    }
}
