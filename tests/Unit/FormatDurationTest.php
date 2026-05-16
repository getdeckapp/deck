<?php

use TorMorten\Deck\Support\FormatDuration;

it('formats null as em dash', function () {
    expect(FormatDuration::format(null))->toBe('—');
});

it('formats sub-second durations in milliseconds', function (int $ms, string $expected) {
    expect(FormatDuration::format($ms))->toBe($expected);
})->with([
    'zero' => [0, '0 ms'],
    'single digit' => [42, '42 ms'],
    'just under one second' => [999, '999 ms'],
]);

it('formats seconds', function (int $ms, string $expected) {
    expect(FormatDuration::format($ms))->toBe($expected);
})->with([
    'one second' => [1_000, '1 s'],
    'fractional' => [1_500, '1.5 s'],
    'just under one minute' => [59_900, '59.9 s'],
]);

it('formats minutes', function (int $ms, string $expected) {
    expect(FormatDuration::format($ms))->toBe($expected);
})->with([
    'one minute' => [60_000, '1 m'],
    'fractional' => [90_000, '1.5 m'],
    'just under one hour' => [3_599_000, '60 m'],
]);

it('formats hours and days', function (int $ms, string $expected) {
    expect(FormatDuration::format($ms))->toBe($expected);
})->with([
    'one hour' => [3_600_000, '1 h'],
    'fractional hour' => [5_400_000, '1.5 h'],
    'one day' => [86_400_000, '1 d'],
]);
