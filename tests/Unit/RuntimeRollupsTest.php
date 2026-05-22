<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Presentation\RuntimeRollups;

it('calculates runtime percentiles and failure rate for a job class', function () {
    config()->set('deck.charts.hours', 24);

    $jobClass = 'App\\Jobs\\RollupJob';

    foreach ([100, 200, 300, 400, 500, 600, 700, 800, 900, 10_000] as $duration) {
        createDeckExecution([
            'job_class' => $jobClass,
            'status' => JobExecutionStatus::Completed,
            'duration_ms' => $duration,
            'started_at' => now()->subHour(),
        ]);
    }

    createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Failed,
        'duration_ms' => 150,
        'started_at' => now()->subHour(),
    ]);

    $rollup = RuntimeRollups::make()->forJobClass($jobClass);

    expect($rollup->sampleCount)->toBe(11)
        ->and($rollup->avgMs)->toBe(1332)
        ->and($rollup->p50Ms)->toBe(500)
        ->and($rollup->p95Ms)->toBe(10_000)
        ->and($rollup->failureRate)->toBe(9.1)
        ->and($rollup->failedCount)->toBe(1)
        ->and($rollup->completedCount)->toBe(10);
});
