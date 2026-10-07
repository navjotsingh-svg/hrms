<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

        if ($this->leaveType?->lapsesMonthly()) {
            return $this->monthlyAvailable();
        }

        return $this->yearlyRemaining();
    }

    public function yearlyRemaining(): float
    {
        if ($this->leaveType?->isUnlimitedLeave()) {
            return PHP_FLOAT_MAX;
        }

        return max(0, ($this->allocated + $this->adjusted) - $this->used - $this->pending);
    }

    private function monthlyAvailable(): float
    {
        $grant = $this->leaveType?->monthlyGrant();

        if ($grant === null) {
            return $this->yearlyRemaining();
        }

        $today = Carbon::now();

        if ((int) $this->year < (int) $today->year) {
            return 0;
        }

        if ((int) $this->year > (int) $today->year) {
            return min($grant, $this->yearlyRemaining());
        }

        $usedThisMonth = (float) LeaveRequestDay::query()
            ->whereHas('leaveRequest', function ($query) {
                $query->where('employee_id', $this->employee_id)
                    ->where('leave_type_id', $this->leave_type_id)
                    ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED]);
            })
            ->whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->sum('day_value');

        return min($this->yearlyRemaining(), max(0, round($grant - $usedThisMonth, 1)));
    }
}
