<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'timezone',
        'break_duration_minutes',
        'is_overnight',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'break_duration_minutes' => 'integer',
            'is_overnight' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getTimeRangeAttribute(): string
    {
        return sprintf('%s - %s', $this->formatTime($this->start_time), $this->formatTime($this->end_time));
    }

    public function getTimingSummaryAttribute(): string
    {
        $summary = $this->time_range;

        if ($this->break_duration_minutes > 0) {
            $summary .= ' · Break '.$this->break_duration_minutes.' min';
        }

        if ($this->is_overnight) {
            $summary .= ' · Overnight';
        }

        if ($this->timezone) {
            $summary .= ' · '.$this->timezone;
        }

        return $summary;
    }

    public function requiredWorkMinutes(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $start = Carbon::createFromFormat('H:i:s', strlen($this->start_time) === 5 ? $this->start_time.':00' : $this->start_time);
        $end = Carbon::createFromFormat('H:i:s', strlen($this->end_time) === 5 ? $this->end_time.':00' : $this->end_time);

        if ($this->is_overnight) {
            $end->addDay();
        }

        return max(0, $start->diffInMinutes($end));
    }

    private function formatTime(?string $time): string
    {
        if (! $time) {
            return '—';
        }

        return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time.':00' : $time)->format('g:i A');
    }
}
