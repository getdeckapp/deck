<?php

use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\Events\JobProcessing;
use TorMorten\Deck\Listeners\PreventBlockedQueueJobs;
use TorMorten\Deck\Listeners\RecordJobExecution;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobClassIdentifierRegistry;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('releases and deletes blocked jobs before the worker runs handle', function () {
    config()->set('deck.block_release_seconds', 90);

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
    $queueJob->shouldReceive('getConnectionName')->andReturn('sync');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('release')->once()->with(90);
    $queueJob->shouldReceive('isDeleted')->andReturn(false);
    $queueJob->shouldReceive('delete')->once();

    app(PreventBlockedQueueJobs::class)->handle(new JobProcessing('sync', $queueJob));

    app(RecordJobExecution::class)->handleProcessing(new JobProcessing('sync', $queueJob));

    expect(JobExecution::query()->count())->toBe(0);
});

it('does not run handle for blocked jobs on the sync connection', function () {
    config()->set('queue.default', 'sync');

    JobClassBlock::block(SuccessfulTestJob::class);

    SuccessfulTestJob::dispatch();

    expect(JobExecution::query()->where('job_class', SuccessfulTestJob::class)->exists())->toBeFalse()
        ->and(JobClassStat::query()->where('job_class', SuccessfulTestJob::class)->exists())->toBeFalse();
});

it('runs handle after the block is removed', function () {
    config()->set('queue.default', 'sync');

    JobClassBlock::block(SuccessfulTestJob::class);
    JobClassBlock::unblock(SuccessfulTestJob::class);

    SuccessfulTestJob::dispatch();

    expect(JobExecution::query()->where('job_class', SuccessfulTestJob::class)->exists())->toBeTrue();
});

it('blocks jobs when only the display name was blocked in deck', function () {
    $displayName = 'deck-test-display-name';
    $jobClass = SuccessfulTestJob::class;

    JobClassIdentifierRegistry::link($displayName, $jobClass);
    JobClassBlock::block($displayName);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('resolveName')->andReturn($displayName);
    $queueJob->shouldReceive('resolveQueuedJobClass')->andReturn($jobClass);
    $queueJob->shouldReceive('release')->once();
    $queueJob->shouldReceive('isDeleted')->andReturn(false);
    $queueJob->shouldReceive('delete')->once();

    app(PreventBlockedQueueJobs::class)->handle(new JobProcessing('redis', $queueJob));
});
