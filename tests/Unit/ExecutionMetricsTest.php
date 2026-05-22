<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Presentation\ExecutionMetrics;

it('builds hourly job volume from executions', function () {
    createDeckExecution([
        'started_at' => now()->subHours(2)->startOfHour()->addMinutes(15),
        'status' => JobExecutionStatus::Completed,
    ]);

    createDeckExecution([
        'started_at' => now()->subHours(2)->startOfHour()->addMinutes(30),
        'status' => JobExecutionStatus::Completed,
    ]);

    createDeckExecution([
        'started_at' => now()->subHour()->startOfHour()->addMinutes(10),
        'status' => JobExecutionStatus::Failed,
    ]);

    $volume = ExecutionMetrics::make()->hourlyJobVolume();

    expect($volume->sum('value'))->toBeGreaterThanOrEqual(3);
});

it('builds hourly average duration from completed runs', function () {
    createDeckExecution([
        'started_at' => now()->subHours(3)->startOfHour()->addMinutes(5),
        'duration_ms' => 1_000,
        'status' => JobExecutionStatus::Completed,
    ]);

    createDeckExecution([
        'started_at' => now()->subHours(3)->startOfHour()->addMinutes(20),
        'duration_ms' => 3_000,
        'status' => JobExecutionStatus::Completed,
    ]);

    $duration = ExecutionMetrics::make()->hourlyAverageDuration();

    expect($duration->contains(fn (array $point): bool => $point['value'] > 0))->toBeTrue();
});

it('includes older executions across the full chart window', function () {
    config(['deck.charts.hours' => 24]);

    createDeckExecution([
        'started_at' => now()->subHours(20)->startOfHour()->addMinutes(10),
        'status' => JobExecutionStatus::Completed,
    ]);

    $volume = ExecutionMetrics::make()->hourlyJobVolume();

    expect($volume->sum('value'))->toBeGreaterThanOrEqual(1);
});
