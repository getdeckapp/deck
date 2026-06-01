<?php

use Deck\Deck\Cancellation\JobCancellation;
use Deck\Deck\Deck;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Support\Facades\Bus;

it('cancels a running execution by uuid', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    expect(app(Deck::class)->cancelExecution($execution->uuid, $execution->attempt))->toBeTrue()
        ->and(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('refuses to cancel a completed execution by uuid', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    expect(app(Deck::class)->cancelExecution($execution->uuid, $execution->attempt))->toBeFalse();
});

it('retries a failed execution through the deck class', function () {
    Bus::fake();

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'job_class' => SuccessfulTestJob::class,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    $result = app(Deck::class)->retryExecution($execution->uuid, $execution->attempt);

    expect($result->success)->toBeTrue();
});

it('returns zero when cancelling all running for a class with none running', function () {
    $jobClass = 'App\\Jobs\\NothingRunningJob';

    createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Completed,
    ]);

    expect(app(Deck::class)->cancelAllRunningForClass($jobClass))->toBe(0);
});
