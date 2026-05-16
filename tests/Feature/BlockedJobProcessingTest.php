<?php

use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\Events\JobProcessing;
use TorMorten\Deck\Listeners\RecordJobExecution;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('releases blocked jobs before recording an execution', function () {
    config()->set('deck.block_release_seconds', 90);

    JobClassBlock::block(SuccessfulTestJob::class);

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('uuid')->andReturn((string) str()->uuid());
    $queueJob->shouldReceive('resolveName')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('payload')->andReturn(['uuid' => (string) str()->uuid()]);
    $queueJob->shouldReceive('getConnectionName')->andReturn('sync');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('release')->once()->with(90);

    app(RecordJobExecution::class)->handleProcessing(new JobProcessing('sync', $queueJob));

    expect(JobExecution::query()->count())->toBe(0);
});
