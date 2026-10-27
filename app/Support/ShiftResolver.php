<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ShiftResolver
{
    /**
     * @param array<int, array{id:int, start_time:string, end_time:string}> $shifts  format jam "H:i" atau "H:i:s"
     */
    public static function resolve(array $shifts, DateTimeInterface $at, string $timezone): ?int
    {
        $local = (new DateTimeImmutable('@' . $at->getTimestamp()))->setTimezone(new DateTimeZone($timezone));
        $now = (int) $local->format('G') * 60 + (int) $local->format('i');

        foreach ($shifts as $shift) {
            $start = self::minutes($shift['start_time']);
            $end = self::minutes($shift['end_time']);

            if ($start === $end) {
                return (int) $shift['id']; // shift 24 jam
            }
            $inside = $start < $end
                ? ($now >= $start && $now < $end)
                : ($now >= $start || $now < $end); // melewati tengah malam
            if ($inside) {
                return (int) $shift['id'];
            }
        }

        return null;
    }

    private static function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }
}
