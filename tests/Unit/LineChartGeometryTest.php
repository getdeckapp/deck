<?php

use Deck\Deck\Presentation\LineChartGeometry;

it('builds line and area paths from data points', function () {
    $chart = LineChartGeometry::build([
        ['label' => '10:00', 'value' => 0],
        ['label' => '11:00', 'value' => 10],
        ['label' => '12:00', 'value' => 5],
    ], fn (int $value): string => (string) $value);

    expect($chart['linePath'])->toStartWith('M')
        ->and($chart['linePath'])->toContain('L')
        ->and($chart['areaPath'])->toEndWith('Z')
        ->and($chart['points'])->toHaveCount(3)
        ->and($chart['xTicks'])->not->toBeEmpty();
});

it('uses a separate formatter for tooltip labels', function () {
    $chart = LineChartGeometry::build(
        [['label' => '10:00', 'value' => 3]],
        fn (int $value): string => (string) $value,
        fn (int $value): string => "{$value} jobs executed",
    );

    expect($chart['points'][0]['formatted'])->toBe('3 jobs executed');
});

it('formats y axis ticks using the value formatter', function () {
    $chart = LineChartGeometry::build([
        ['label' => '10:00', 'value' => 1_500],
    ], fn (int $value): string => "{$value}ms");

    expect($chart['yTicks'][0]['label'])->toBe('1500ms')
        ->and($chart['yTicks'][2]['label'])->toBe('0ms');
});
