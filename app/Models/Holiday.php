<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    public const FREQUENCY_FIXED = 'fixed';

    public const FREQUENCY_VARIABLE = 'variable';

    public const DURATION_SINGLE = 'single';

    public const DURATION_RANGE = 'range';

    public const FIXED_YEAR = 2000;

    protected $fillable = [
        'company_id',
        'name',
        'frequency',
        'duration',
        'date',
        'from_date',
        'to_date',
        'type',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Holiday $holiday) {
            if ($holiday->from_date) {
                $holiday->date = $holiday->from_date;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isFixed(): bool
    {
        return $this->frequency === self::FREQUENCY_FIXED;
    }

    public function isSingleDay(): bool
    {
        return $this->duration === self::DURATION_SINGLE;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function resolvedBoundsForYear(int $year): array
    {
        if (! $this->isFixed()) {
            return [$this->from_date->copy()->startOfDay(), $this->to_date->copy()->startOfDay()];
        }

        $from = Carbon::create($year, $this->from_date->month, $this->from_date->day)->startOfDay();
        $endYear = $year;

        if ($this->spansYearEnd()) {
            $endYear = $year + 1;
        }

        $to = Carbon::create($endYear, $this->to_date->month, $this->to_date->day)->startOfDay();

        return [$from, $to];
    }

    public function coversDateResolved(string $date): bool
    {
        $target = Carbon::parse($date)->startOfDay();

        if ($this->isFixed()) {
            [$from, $to] = $this->resolvedBoundsForYear($target->year);

            return $target->betweenIncluded($from, $to);
        }

        return $target->toDateString() >= $this->from_date->toDateString()
            && $target->toDateString() <= $this->to_date->toDateString();
    }

    public function spansYearEnd(): bool
    {
        if ($this->isSingleDay()) {
            return false;
        }

        $fromMonth = (int) $this->from_date->format('m');
        $fromDay = (int) $this->from_date->format('d');
        $toMonth = (int) $this->to_date->format('m');
        $toDay = (int) $this->to_date->format('d');

        return $toMonth < $fromMonth || ($toMonth === $fromMonth && $toDay < $fromDay);
    }

    public function displayDateLabel(): string
    {
        if ($this->isFixed()) {
            if ($this->isSingleDay()) {
                return $this->from_date->format('d M').' (every year)';
            }

            return $this->from_date->format('d M').' – '.$this->to_date->format('d M').' (every year)';
        }

        if ($this->isSingleDay()) {
            return $this->from_date->format('d M Y');
        }

        return $this->from_date->format('d M Y').' – '.$this->to_date->format('d M Y');
    }
}
