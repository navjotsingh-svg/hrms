<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DateRangePresetService
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_THIS_WEEK = 'this_week';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_CUSTOM = 'custom';

    public const DEFAULT_PRESET = self::PRESET_THIS_MONTH;

    /** @return array<int, array{key: string, label: string}> */
    public function presets(): array
    {
        return [
            ['key' => self::PRESET_TODAY, 'label' => 'Today'],
            ['key' => self::PRESET_YESTERDAY, 'label' => 'Yesterday'],
            ['key' => self::PRESET_THIS_WEEK, 'label' => 'This Week'],
            ['key' => self::PRESET_THIS_MONTH, 'label' => 'This Month'],
            ['key' => self::PRESET_CUSTOM, 'label' => 'Custom'],
        ];
    }

    /**
     * @return array{preset: string, from_date: string, to_date: string, from: Carbon, to: Carbon}
     */
    public function resolve(array $input = [], ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $preset = (string) ($input['range'] ?? $input['date_range'] ?? self::DEFAULT_PRESET);

        if (! in_array($preset, array_column($this->presets(), 'key'), true)) {
            $preset = self::DEFAULT_PRESET;
        }

        if ($preset === self::PRESET_CUSTOM) {
            $fromInput = $input['from_date'] ?? null;
            $toInput = $input['to_date'] ?? null;

            if (empty($fromInput) || empty($toInput)) {
                throw ValidationException::withMessages([
                    'from_date' => ['Custom range requires from and to dates.'],
                ]);
            }

            $from = Carbon::parse($fromInput)->startOfDay();
            $to = Carbon::parse($toInput)->endOfDay();

            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'to_date' => ['To date must be on or after from date.'],
                ]);
            }
        } else {
            [$from, $to] = match ($preset) {
                self::PRESET_TODAY => [$now->copy(), $now->copy()->endOfDay()],
                self::PRESET_YESTERDAY => [
                    $now->copy()->subDay()->startOfDay(),
                    $now->copy()->subDay()->endOfDay(),
                ],
                self::PRESET_THIS_WEEK => [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek()->endOfDay(),
                ],
                default => [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth()->endOfDay(),
                ],
            };
        }

        return [
            'preset' => $preset,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'from' => $from,
            'to' => $to,
        ];
    }

    /** @return array<string, string> */
    public function toFilterParams(array $range): array
    {
        return [
            'from_date' => $range['from_date'],
            'to_date' => $range['to_date'],
            'allow_cross_year' => true,
        ];
    }
}
