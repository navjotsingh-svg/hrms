<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class DateTimeLabel
{
    public static function format(?CarbonInterface $value, string $empty = '—'): string
    {
        if ($value === null) {
            return $empty;
        }

        return $value->format('d M Y')."\n".$value->format('h:i A');
    }
}
