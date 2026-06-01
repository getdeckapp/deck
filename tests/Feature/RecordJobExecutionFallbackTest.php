<?php

use Deck\Deck\Core\DeckInstallation;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Listeners\RecordJobExecution;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessed;

it('records completed execution when the running row was never written', function () {
    $uuid = (string) str()->uuid();

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'uuid' => $uuid,
        'displayName' => SuccessfulTestJob::class,
        'data' => ['commandName' => SuccessfulTestJob::class],
    ]);
    $queueJob->shouldReceive('getName')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('resolveQueuedJobClass')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('resolveName')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('getConnectionName')->andReturn('redis');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('isDeletedOrReleased')->andReturn(true);
    $queueJob->shouldReceive('hasFailed')->andReturn(false);

    app(RecordJobExecution::class)->handleProcessed(new JobProcessed('redis', $queueJob));

    $execution = JobExecution::query()
        ->where('uuid', $uuid)
        ->where('project', DeckInstallation::project())
        ->where('environment', DeckInstallation::environment())
        ->first();

    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe(JobExecutionStatus::Completed)
        ->and($execution->finished_at)->not->toBeNull();
});

it('records terminal status from job attempted when execution is still running', function () {
    $uuid = (string) str()->uuid();

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'uuid' => $uuid,
        'displayName' => SuccessfulTestJob::class,
        'data' => ['commandName' => SuccessfulTestJob::class],
    ]);
    $queueJob->shouldReceive('getName')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('resolveQueuedJobClass')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('resolveName')->andReturn(SuccessfulTestJob::class);
    $queueJob->shouldReceive('getConnectionName')->andReturn('redis');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('isDeletedOrReleased')->andReturn(true);
    $queueJob->shouldReceive('hasFailed')->andReturn(false);

    JobExecution::query()->create([
        'project' => DeckInstallation::project(),
        'environment' => DeckInstallation::environment(),
        'uuid' => $uuid,
        'job_class' => SuccessfulTestJob::class,
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
        'attempt' => 1,
        'started_at' => now()->subSecond(),
        'created_at' => now(),
    ]);

    app(RecordJobExecution::class)->handleJobAttempted(new JobAttempted('redis', $queueJob));

    expect(JobExecution::query()->where('uuid', $uuid)->value('status'))
        ->toBe(JobExecutionStatus::Completed);
});
