<?php

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\CommandApplicator;
use Deck\Deck\Cloud\CommandPoller;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Deck;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Support\JobCancellation;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    enableDeckCloudForTests();
    config()->set('deck.project', 'billing-api');
    config()->set('deck.environment', 'production');

    resetDeckCloudSyncThrottle();
});

it('is disabled when cloud is not enabled', function () {
    config()->set('deck.cloud.enabled', false);

    expect(DeckCloud::commandsEnabled())->toBeFalse();
});

it('pulls applies and acks cancel execution commands', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_cancel_1',
                    'type' => 'cancel_execution',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'uuid' => $execution->uuid,
                        'attempt' => $execution->attempt,
                        'connection' => $execution->connection,
                        'queue' => $execution->queue,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect(JobCancellation::isCancelled($execution->uuid))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/agent/commands')
            && $request['project'] === 'billing-api'
            && $request['environment'] === 'production';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
            && $request['results'][0]['id'] === 'cmd_cancel_1'
            && $request['results'][0]['status'] === 'applied';
    });
});

it('applies force cancel execution commands', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_force_1',
                    'type' => 'force_cancel_execution',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'uuid' => $execution->uuid,
                        'attempt' => $execution->attempt,
                        'connection' => $execution->connection,
                        'queue' => $execution->queue,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect($execution->fresh()->status)->toBe(JobExecutionStatus::Cancelled)
        ->and(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('applies cancel pending commands', function () {
    $uuid = (string) str()->uuid();

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_pending_1',
                    'type' => 'cancel_pending',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'uuid' => $uuid,
                        'connection' => 'redis',
                        'queue' => 'default',
                        'force' => false,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect(JobCancellation::isCancelled($uuid))->toBeTrue();
});

it('acks failed when execution is not running', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_fail_1',
                    'type' => 'cancel_execution',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'uuid' => $execution->uuid,
                        'attempt' => $execution->attempt,
                        'connection' => $execution->connection,
                        'queue' => $execution->queue,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    Http::assertSent(fn ($request) => $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
        && $request['results'][0]['status'] === 'failed'
        && str_contains($request['results'][0]['message'], 'not running'));
});

it('acks ignored when the same cancel command is applied twice', function () {
    $uuid = (string) str()->uuid();
    JobCancellation::cancel($uuid);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_dup_1',
                    'type' => 'cancel_pending',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'uuid' => $uuid,
                        'connection' => 'redis',
                        'queue' => 'default',
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
            && ($request->data()['results'][0]['status'] ?? null) === 'ignored';
    });
});

it('deduplicates duplicate command ids in a single pull batch', function () {
    $uuid = (string) str()->uuid();

    $deck = Mockery::mock(Deck::class)->makePartial();
    $deck->shouldReceive('cancelPending')->once();
    $this->app->instance(Deck::class, $deck);
    $this->app->forgetInstance(CommandApplicator::class);
    $this->app->forgetInstance(CommandPoller::class);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_dup_batch',
                    'type' => 'cancel_pending',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => ['uuid' => $uuid, 'connection' => 'redis', 'queue' => 'default'],
                ],
                [
                    'id' => 'cmd_dup_batch',
                    'type' => 'cancel_pending',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => ['uuid' => $uuid, 'connection' => 'redis', 'queue' => 'default'],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
            && count($request->data()['results'] ?? []) === 1;
    });
});

it('polls commands when worker sync is throttled', function () {
    $poller = Mockery::mock(CommandPoller::class);
    $poller->shouldReceive('poll')->once();
    app()->instance(CommandPoller::class, $poller);

    $throttle = Mockery::mock(\Deck\Deck\Cloud\SyncThrottle::class);
    $throttle->shouldReceive('shouldSync')->with('workers', 'redis:default')->andReturnFalse();
    $throttle->shouldReceive('shouldSync')->with('commands', 'installation')->andReturnTrue();
    app()->instance(\Deck\Deck\Cloud\SyncThrottle::class, $throttle);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');
});

it('polls commands after worker reporting on the same sync tick', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 0], 202),
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response(['commands' => []]),
    ]);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/api/v1/agent/commands'));
});

it('applies block class commands from cloud', function () {
    $jobClass = \Deck\Deck\Tests\Fixtures\SuccessfulTestJob::class;

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_block_1',
                    'type' => 'block_class',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'job_class' => $jobClass,
                        'cancel_running' => false,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect(app(Deck::class)->isClassBlocked($jobClass))->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
        && ($request->data()['results'][0]['status'] ?? null) === 'applied');
});

it('applies timed block class commands from cloud', function () {
    $jobClass = \Deck\Deck\Tests\Fixtures\SuccessfulTestJob::class;
    $until = now()->addHour()->toIso8601String();

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_block_timed',
                    'type' => 'block_class',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'job_class' => $jobClass,
                        'until' => $until,
                        'cancel_running' => false,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect(app(Deck::class)->isClassBlocked($jobClass))->toBeTrue()
        ->and(app(Deck::class)->classBlockedUntil($jobClass)?->toIso8601String())->toBe($until);
});

it('applies unblock class commands from cloud', function () {
    $jobClass = \Deck\Deck\Tests\Fixtures\SuccessfulTestJob::class;

    app(Deck::class)->blockClass($jobClass, now()->addHour());

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_unblock_1',
                    'type' => 'unblock_class',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'job_class' => $jobClass,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect(app(Deck::class)->isClassBlocked($jobClass))->toBeFalse();
});

it('applies cancel all running for class commands from cloud', function () {
    $execution = createDeckExecution([
        'job_class' => 'App\\Jobs\\SyncInvoices',
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_cancel_class_1',
                    'type' => 'cancel_all_running_for_class',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'job_class' => 'App\\Jobs\\SyncInvoices',
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    expect(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('acks failed for unknown command types', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_unknown',
                    'type' => 'restart_supervisor',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    Http::assertSent(fn ($request) => $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
        && $request['results'][0]['status'] === 'failed'
        && str_contains($request['results'][0]['message'], 'Unknown command type'));
});

it('does not poll commands when command sync is disabled', function () {
    config()->set('deck.cloud.commands.enabled', false);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 0], 202),
    ]);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/agent/commands'));
});

it('applies retry execution commands', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'finished_at' => now(),
        'duration_ms' => 1000,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'boom',
    ]);

    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response([
            'commands' => [
                [
                    'id' => 'cmd_retry_1',
                    'type' => 'retry_execution',
                    'project' => 'billing-api',
                    'environment' => 'production',
                    'issued_at' => now()->toIso8601String(),
                    'payload' => [
                        'uuid' => $execution->uuid,
                        'attempt' => $execution->attempt,
                        'connection' => $execution->connection,
                        'queue' => $execution->queue,
                    ],
                ],
            ],
        ]),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
    ]);

    app(CommandPoller::class)->poll();

    Http::assertSent(fn ($request) => $request->url() === 'https://cloud.deck.test/api/v1/agent/commands/ack'
        && $request['results'][0]['id'] === 'cmd_retry_1'
        && in_array($request['results'][0]['status'], ['applied', 'failed'], true));
});
