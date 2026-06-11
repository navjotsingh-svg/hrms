<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestDay extends Model
{
    public const SESSION_FULL = 'full_day';

    public const SESSION_FIRST_HALF = 'first_half';

    public const SESSION_SECOND_HALF = 'second_half';

    public const SESSION_HOURLY = 'hourly';

    protected $fillable = [
        'leave_request_id',
        'date',
        'session',
        'duration_minutes',
        'day_value',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'day_value' => 'float',
            'duration_minutes' => 'integer',
        ];
    }

    public function sessionLabel(): string
    {
        return match ($this->session) {
            self::SESSION_FULL => 'Full Day',
            self::SESSION_FIRST_HALF => 'First Half',
            self::SESSION_SECOND_HALF => 'Second Half',
            self::SESSION_HOURLY => $this->duration_minutes
                ? self::formatDurationLabel($this->duration_minutes)
                : 'Short Leave',
            default => ucfirst(str_replace('_', ' ', $this->session)),
        };
    }

    public static function formatDurationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return "{$remaining} min";
        }

        if ($remaining === 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$hours}h {$remaining}m";
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
