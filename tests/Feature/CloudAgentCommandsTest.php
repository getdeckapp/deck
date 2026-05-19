<?php

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\CommandApplicator;
use Deck\Deck\Cloud\CommandPoller;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\SyncThrottle;
use Deck\Deck\Deck;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Support\JobCancellation;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('deck.cloud.enabled', true);
    config()->set('deck.cloud.url', 'https://cloud.deck.test');
    config()->set('deck.cloud.api_key', 'test-api-key');
    config()->set('deck.cloud.workers.interval_seconds', 30);
    config()->set('deck.cloud.commands.enabled', true);
    config()->set('deck.project', 'billing-api');
    config()->set('deck.environment', 'production');

    app(SyncThrottle::class)->reset();
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

it('polls commands after worker reporting on the same sync tick', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 0], 202),
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response(['commands' => []]),
    ]);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/api/v1/agent/commands'));
});

it('does not poll commands when command sync is disabled', function () {
    config()->set('deck.cloud.commands.enabled', false);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 0], 202),
    ]);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/v1/agent/commands'));
});
