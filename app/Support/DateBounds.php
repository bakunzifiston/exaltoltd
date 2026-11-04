<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Bounds for filtering a DATE column by a range of days.
 *
 * DATE columns are not stored the same way everywhere: SQLite keeps whatever string was
 * written, which is "2025-01-31" from a form but "2025-01-31 00:00:00" through a date cast.
 * Comparing either against a bound with a time, or a bound without one, quietly drops the
 * first or last day of the range. A bare start date and an end date at 23:59:59 include
 * both ends on SQLite, MySQL and Postgres alike, and unlike whereDate() keep the index.
 */
class DateBounds
{
    /** @return array{0: string, 1: string} */
    public static function between(DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        return [self::day($from), self::endOfDay($to)];
    }

    public static function endOfDay(DateTimeInterface|string $date): string
    {
        return self::day($date).' 23:59:59';
    }

    private static function day(DateTimeInterface|string $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr($date, 0, 10);
    }
}
