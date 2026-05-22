<?php

use Deck\Deck\Core\DeckInstallation;
use Deck\Deck\Listeners\RecordJobExecution;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Recording\QueuedJobMetadata;
use Deck\Deck\Recording\QueuedJobResolver;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobProcessed;

it('prefers mailable display name over SendQueuedMailable handler', function () {
    $mailableClass = 'App\\Mail\\Campaign';
    $name = 'Illuminate\\Queue\\CallQueuedHandler@call';
    $payload = [
        'uuid' => (string) str()->uuid(),
        'displayName' => $mailableClass,
        'job' => $name,
        'data' => ['commandName' => SendQueuedMailable::class],
    ];

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('payload')->andReturn($payload);
    $queueJob->shouldReceive('getName')->andReturn($name);
    $queueJob->shouldReceive('resolveQueuedJobClass')->andReturn(SendQueuedMailable::class);
    $queueJob->shouldReceive('resolveName')->andReturn($mailableClass);

    expect(QueuedJobResolver::resolveClass($queueJob))->toBe($mailableClass)
        ->and(QueuedJobResolver::resolveHandlerClass($queueJob))->toBe(SendQueuedMailable::class);
});

it('resolves job class from serialized command payload', function () {
    $name = 'Illuminate\\Queue\\CallQueuedHandler@call';
    $payload = [
        'uuid' => (string) str()->uuid(),
        'displayName' => SuccessfulTestJob::class,
        'job' => $name,
        'data' => ['commandName' => SuccessfulTestJob::class],
    ];

    expect(QueuedJobResolver::resolveClassFromPayload($name, $payload))->toBe(SuccessfulTestJob::class)
        ->and(QueuedJobResolver::resolveDisplayNameFromPayload($name, $payload))->toBe(SuccessfulTestJob::class);
});

it('records completed execution for queue jobs without resolveQueuedJobClass', function () {
    $uuid = (string) str()->uuid();
    $name = 'Illuminate\\Queue\\CallQueuedHandler@call';

    $queueJob = Mockery::mock(QueueJobContract::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'uuid' => $uuid,
        'displayName' => SuccessfulTestJob::class,
        'job' => $name,
        'data' => ['commandName' => SuccessfulTestJob::class],
    ]);
    $queueJob->shouldReceive('getName')->andReturn($name);
    $queueJob->shouldReceive('getConnectionName')->andReturn('redis');
    $queueJob->shouldReceive('getQueue')->andReturn('default');
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('isDeletedOrReleased')->andReturn(true);
    $queueJob->shouldReceive('hasFailed')->andReturn(false);
    $queueJob->shouldReceive('resolveName')->andReturn(SuccessfulTestJob::class);

    expect(QueuedJobMetadata::fromQueueJob($queueJob)->jobClass)->toBe(SuccessfulTestJob::class);

    app(RecordJobExecution::class)->handleProcessed(new JobProcessed('redis', $queueJob));

    $execution = JobExecution::query()
        ->where('uuid', $uuid)
        ->where('project', DeckInstallation::project())
        ->where('environment', DeckInstallation::environment())
        ->first();

    expect($execution)->not->toBeNull()
        ->and($execution->job_class)->toBe(SuccessfulTestJob::class)
        ->and($execution->status->value)->toBe('completed');
});
