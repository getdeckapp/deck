<?php

use TorMorten\Deck\Deck;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Support\JobCancellation;

it('cancels a running execution by id', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    expect(app(Deck::class)->cancelExecution($execution->id))->toBeTrue()
        ->and(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('refuses to cancel a completed execution by id', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    expect(app(Deck::class)->cancelExecution($execution->id))->toBeFalse();
});
