<?php

namespace App\Support;

use DateTime;
use DateTimeZone;

class TimezoneOptions
{
    /** @return list<string> */
    public static function identifiers(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    /** @return array<string, list<string>> */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::identifiers() as $timezone) {
            $parts = explode('/', $timezone, 2);
            $region = $parts[0] ?? 'Other';
            $groups[$region][] = $timezone;
        }

        ksort($groups);

        foreach ($groups as &$timezones) {
            sort($timezones);
        }

        return $groups;
    }

    public static function label(string $timezone): string
    {
        try {
            $zone = new DateTimeZone($timezone);
            $offset = $zone->getOffset(new DateTime('now', new DateTimeZone('UTC')));
            $sign = $offset >= 0 ? '+' : '-';
            $absolute = abs($offset);
            $hours = intdiv($absolute, 3600);
            $minutes = intdiv($absolute % 3600, 60);

            return sprintf('(UTC%s%02d:%02d) %s', $sign, $hours, $minutes, $timezone);
        } catch (\Throwable) {
            return $timezone;
        }
    }

    public static function isValid(string $timezone): bool
    {
        return in_array($timezone, self::identifiers(), true);
    }
}
