<?php

namespace TorMorten\Deck\Support;

class FormatDuration
{
    public static function format(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '—';
        }

        if ($milliseconds < 1_000) {
            return number_format($milliseconds).' ms';
        }

        $seconds = $milliseconds / 1_000;

        if ($seconds < 60) {
            return self::formatUnit($seconds, 's');
        }

        $minutes = $seconds / 60;

        if ($minutes < 60) {
            return self::formatUnit($minutes, 'm');
        }

        $hours = $minutes / 60;

        if ($hours < 24) {
            return self::formatUnit($hours, 'h');
        }

        $days = $hours / 24;

        return self::formatUnit($days, 'd');
    }

    private static function formatUnit(float $value, string $unit): string
    {
        if ($value >= 100) {
            return (int) round($value).' '.$unit;
        }

        $formatted = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return $formatted.' '.$unit;
    }
}
