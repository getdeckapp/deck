<?php

use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Support\Facades\Queue;
use TorMorten\Deck\Bus\DeckDispatcher;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\InterceptBlockedQueueJob;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobClassIdentifierRegistry;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('uses the deck bus dispatcher', function () {
    expect(app(BusDispatcher::class))->toBeInstanceOf(DeckDispatcher::class);
});

it('does not push blocked jobs onto the queue', function () {
    Queue::fake();

    JobClassBlock::block(SuccessfulTestJob::class);

    SuccessfulTestJob::dispatch();

    Queue::assertNothingPushed();

    expect(JobExecution::query()->where('status', JobExecutionStatus::Blocked)->exists())->toBeTrue();
});

it('records blocked jobs at dispatch without running handle', function () {
    config()->set('queue.default', 'sync');

    JobClassBlock::block(SuccessfulTestJob::class);

    SuccessfulTestJob::dispatch();

    $execution = JobExecution::query()->where('job_class', SuccessfulTestJob::class)->first();

    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe(JobExecutionStatus::Blocked)
        ->and($execution->finished_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBe(0);
});

it('deletes blocked jobs so the worker skips handle', function () {
    JobClassBlock::block(SuccessfulTestJob::class);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('uuid')->andReturn((string) str()->uuid());
    $queueJob->shouldReceive('resolveName')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('resolveQueuedJobClass')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'uuid' => (string) str()->uuid(),
        'displayName' => SuccessfulTestJob::class,
        'data' => ['commandName' => SuccessfulTestJob::class],
    ]);
    $queueJob->shouldReceive('getConnectionName')->andReturn('redis');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('isDeleted')->andReturn(false);
    $queueJob->shouldReceive('delete')->once();

    expect(InterceptBlockedQueueJob::intercept($queueJob))->toBeTrue()
        ->and(JobExecution::query()->where('status', JobExecutionStatus::Blocked)->count())->toBe(1);
});

it('runs handle after the block is removed', function () {
    config()->set('queue.default', 'sync');

    JobClassBlock::block(SuccessfulTestJob::class);
    JobClassBlock::unblock(SuccessfulTestJob::class);

    SuccessfulTestJob::dispatch();

    expect(JobExecution::query()->where('job_class', SuccessfulTestJob::class)->first()?->status)
        ->toBe(JobExecutionStatus::Completed);
});

it('blocks jobs when only the display name was blocked in deck', function () {
    $displayName = 'deck-test-display-name';
    $jobClass = SuccessfulTestJob::class;

    JobClassIdentifierRegistry::link($displayName, $jobClass);
    JobClassBlock::block($displayName);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('uuid')->andReturn((string) str()->uuid());
    $queueJob->shouldReceive('resolveName')->andReturn($displayName);
    $queueJob->shouldReceive('resolveQueuedJobClass')->andReturn($jobClass);
    $queueJob->shouldReceive('payload')->andReturn([
        'uuid' => (string) str()->uuid(),
        'displayName' => $displayName,
        'data' => ['commandName' => $jobClass],
    ]);
    $queueJob->shouldReceive('getConnectionName')->andReturn('redis');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('isDeleted')->andReturn(false);
    $queueJob->shouldReceive('delete')->once();

    InterceptBlockedQueueJob::intercept($queueJob);

    expect(JobExecution::query()->where('status', JobExecutionStatus::Blocked)->exists())->toBeTrue();
});

it('updates class stats when a job is blocked', function () {
    config()->set('queue.default', 'sync');

    JobClassBlock::block(SuccessfulTestJob::class);

    SuccessfulTestJob::dispatch();

    $stat = JobClassStat::query()->where('job_class', SuccessfulTestJob::class)->first();

    expect($stat)->not->toBeNull()
        ->and($stat->last_status)->toBe(JobExecutionStatus::Blocked);
});
