<?php

namespace TorMorten\Deck\Support;

class LineChartGeometry
{
    public const WIDTH = 480;

    public const HEIGHT = 200;

    public const PAD_LEFT = 48;

    public const PAD_RIGHT = 12;

    public const PAD_TOP = 16;

    public const PAD_BOTTOM = 28;

    /**
     * @param  array<int, array{label: string, value: int, at?: string}>  $data
     * @return array{
     *     points: array<int, array{x: float, y: float, label: string, value: int, formatted: string, at: string|null}>,
     *     linePath: string,
     *     areaPath: string,
     *     yTicks: array<int, array{y: float, label: string}>,
     *     xTicks: array<int, array{x: float, label: string}>,
     *     baseline: float,
     *     max: int,
     * }
     */
    public static function build(array $data, callable $valueFormatter, ?callable $tooltipFormatter = null): array
    {
        $tooltipFormatter ??= $valueFormatter;
        $count = count($data);
        $plotWidth = self::WIDTH - self::PAD_LEFT - self::PAD_RIGHT;
        $plotHeight = self::HEIGHT - self::PAD_TOP - self::PAD_BOTTOM;
        $baseline = self::PAD_TOP + $plotHeight;
        $max = max(1, (int) max(array_column($data, 'value') ?: [0]));

        $points = [];

        foreach (array_values($data) as $index => $point) {
            $x = $count > 1
                ? self::PAD_LEFT + ($index / ($count - 1)) * $plotWidth
                : self::PAD_LEFT + ($plotWidth / 2);
            $y = self::PAD_TOP + $plotHeight - ($point['value'] / $max) * $plotHeight;

            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'label' => $point['label'],
                'value' => (int) $point['value'],
                'formatted' => $tooltipFormatter((int) $point['value']),
                'at' => $point['at'] ?? null,
            ];
        }

        $linePath = self::linePath($points);
        $first = $points[0] ?? ['x' => self::PAD_LEFT];
        $last = $points[array_key_last($points)] ?? $first;
        $areaPath = $linePath !== ''
            ? "{$linePath} L{$last['x']},{$baseline} L{$first['x']},{$baseline} Z"
            : '';

        $yTicks = [
            ['y' => self::PAD_TOP, 'label' => $valueFormatter($max)],
            ['y' => self::PAD_TOP + ($plotHeight / 2), 'label' => $valueFormatter((int) round($max / 2))],
            ['y' => $baseline, 'label' => $valueFormatter(0)],
        ];

        $xTicks = [];

        foreach (self::xTickIndices($count) as $index) {
            $xTicks[] = [
                'x' => $points[$index]['x'],
                'label' => $points[$index]['label'],
            ];
        }

        return [
            'points' => $points,
            'linePath' => $linePath,
            'areaPath' => $areaPath,
            'yTicks' => $yTicks,
            'xTicks' => $xTicks,
            'baseline' => $baseline,
            'max' => $max,
        ];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private static function linePath(array $points): string
    {
        $segments = [];

        foreach ($points as $index => $point) {
            $command = $index === 0 ? 'M' : 'L';
            $segments[] = "{$command}{$point['x']},{$point['y']}";
        }

        return implode(' ', $segments);
    }

    /**
     * @return array<int, int>
     */
    private static function xTickIndices(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [0];
        }

        if ($count <= 6) {
            return range(0, $count - 1);
        }

        return array_values(array_unique([
            0,
            (int) floor(($count - 1) * 0.25),
            (int) floor(($count - 1) * 0.5),
            (int) floor(($count - 1) * 0.75),
            $count - 1,
        ]));
    }
}
