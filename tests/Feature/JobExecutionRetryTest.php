<?php

use Deck\Deck\Cancellation\JobExecutionRetry;
use Deck\Deck\Deck;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Tests\Fixtures\ParameterizedTestJob;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Support\Facades\Bus;

it('returns not found when retrying a missing failed execution', function () {
    $result = app(Deck::class)->retryExecution((string) str()->uuid());

    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('Failed execution not found.');
});

it('refuses to retry a non-failed execution', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    $result = app(Deck::class)->retryExecution($execution->uuid, $execution->attempt);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toBe('Failed execution not found.');
});

it('redispatches parameterless jobs when no failed payload store is available', function () {
    Bus::fake();

    $execution = createDeckExecution([
        'job_class' => SuccessfulTestJob::class,
        'status' => JobExecutionStatus::Failed,
        'connection' => 'sync',
        'queue' => 'default',
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    $result = app(Deck::class)->retryExecution($execution->uuid, $execution->attempt);

    expect($result->success)->toBeTrue()
        ->and($result->message)->toContain('new job instance was dispatched');

    Bus::assertDispatched(SuccessfulTestJob::class);
});

it('refuses to redispatch jobs that require constructor arguments', function () {
    $execution = createDeckExecution([
        'job_class' => ParameterizedTestJob::class,
        'status' => JobExecutionStatus::Failed,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    $result = app(JobExecutionRetry::class)->retry($execution);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('constructor arguments');
});

it('retries the latest failed attempt when attempt is omitted', function () {
    Bus::fake();

    $uuid = (string) str()->uuid();

    createDeckExecution([
        'uuid' => $uuid,
        'attempt' => 1,
        'status' => JobExecutionStatus::Failed,
        'job_class' => SuccessfulTestJob::class,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'first',
    ]);

    createDeckExecution([
        'uuid' => $uuid,
        'attempt' => 2,
        'status' => JobExecutionStatus::Failed,
        'job_class' => SuccessfulTestJob::class,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'second',
    ]);

    $result = app(Deck::class)->retryExecution($uuid);

    expect($result->success)->toBeTrue();

    Bus::assertDispatched(SuccessfulTestJob::class);
});

it('marks failed executions as retryable', function () {
    $failed = createDeckExecution(['status' => JobExecutionStatus::Failed]);
    $completed = createDeckExecution(['status' => JobExecutionStatus::Completed]);

    expect($failed->canRetry())->toBeTrue()
        ->and($completed->canRetry())->toBeFalse();
});
