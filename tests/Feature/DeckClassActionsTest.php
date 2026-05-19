<?php

use Deck\Deck\Deck;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Facades\Deck as DeckFacade;
use Deck\Deck\Support\JobCancellation;
use Deck\Deck\Support\JobClassBlock;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;

it('cancels all running executions for a class', function () {
    $jobClass = 'App\\Jobs\\BulkCancelJob';

    $first = createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);
    $second = createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);
    createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Completed,
    ]);

    $count = app(Deck::class)->cancelAllRunningForClass($jobClass);

    expect($count)->toBe(2)
        ->and(JobCancellation::isCancelled($first->uuid))->toBeTrue()
        ->and(JobCancellation::isCancelled($second->uuid))->toBeTrue();
});

it('blocks a class through the facade and cancels running jobs', function () {
    $jobClass = SuccessfulTestJob::class;

    $running = createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    DeckFacade::blockClass($jobClass, now()->addHour());

    expect(DeckFacade::isClassBlocked($jobClass))->toBeTrue()
        ->and(JobCancellation::isCancelled($running->uuid))->toBeTrue()
        ->and(DeckFacade::classBlockedUntil($jobClass))->not->toBeNull();
});

it('unblocks a class through the facade', function () {
    $jobClass = SuccessfulTestJob::class;

    JobClassBlock::block($jobClass);

    DeckFacade::unblockClass($jobClass);

    expect(DeckFacade::isClassBlocked($jobClass))->toBeFalse();
});
