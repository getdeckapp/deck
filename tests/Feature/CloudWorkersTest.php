<?php

use Deck\Deck\Cloud\Agent\AgentSync;
use Deck\Deck\Cloud\Workers\QueueWorkloadSnapshot;
use Deck\Deck\Cloud\Workers\WorkerReporter;
use Deck\Deck\Cloud\Workers\WorkerSnapshot;
use Deck\Deck\Cloud\Workers\WorkerSnapshotCollector;
use Deck\Deck\Horizon\HorizonSnapshot;
use Deck\Deck\Listeners\SyncCloudAgent;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    enableDeckCloudForTests();
    config()->set('deck.cloud.timeout_seconds', 5);
    config()->set('deck.project', 'billing-api');
    config()->set('deck.environment', 'production');

    resetDeckCloudSyncThrottle();
});

it('is disabled when cloud is not enabled', function () {
    config()->set('deck.cloud.enabled', false);

    expect(AgentSync::isEnabled())->toBeFalse();
});

it('posts worker snapshots to deck cloud', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
    ]);

    app(WorkerReporter::class)->send([
        new WorkerSnapshot(
            supervisor: 'supervisor-1',
            name: 'redis:default',
            connection: 'redis',
            queue: 'default',
            status: 'running',
            processes: 3,
            balance: 'auto',
            memoryMb: 128,
            jobsPerMinute: 42,
            hostname: 'worker-01.test',
            paused: false,
        ),
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cloud.deck.test/api/v1/ingest/workers'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['workers'][0]['project'] === 'billing-api'
            && $request['workers'][0]['environment'] === 'production'
            && $request['workers'][0]['supervisor'] === 'supervisor-1'
            && $request['workers'][0]['status'] === 'running';
    });
});

it('chunks worker snapshots when more than one hundred are reported', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 100], 202),
    ]);

    $snapshots = [];

    for ($index = 0; $index < 101; $index++) {
        $snapshots[] = new WorkerSnapshot(
            supervisor: 'supervisor-1',
            name: 'redis:queue-'.$index,
            connection: 'redis',
            queue: 'queue-'.$index,
            status: 'running',
            processes: 1,
        );
    }

    app(WorkerReporter::class)->send($snapshots);

    Http::assertSentCount(2);
});

it('includes horizon queue workload snapshots on the first worker batch', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1, 'queues_accepted' => 1], 202),
    ]);

    app(WorkerReporter::class)->send([
        new WorkerSnapshot(
            supervisor: 'supervisor-1',
            name: 'redis:default',
            connection: 'redis',
            queue: 'default',
            status: 'running',
            processes: 3,
        ),
    ], [
        new QueueWorkloadSnapshot(
            connection: 'redis',
            queue: 'default',
            length: 142,
            waitSeconds: 45,
            processes: 3,
        ),
    ]);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://cloud.deck.test/api/v1/ingest/workers'
            && ($data['queues'][0]['length'] ?? null) === 142
            && (int) ($data['queues'][0]['wait_seconds'] ?? 0) === 45;
    });
});

it('reports queue workers without horizon', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    app(AgentSync::class)->syncQueueWorker('redis', 'default');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/v1/ingest/workers')
            && ($request->data()['workers'][0]['supervisor'] ?? null) === 'default'
            && ($request->data()['workers'][0]['name'] ?? null) === 'redis:default';
    });
});

it('throttles repeated worker reports', function () {
    config()->set('deck.cloud.commands.enabled', false);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
    ]);

    $sync = app(AgentSync::class);

    $sync->syncQueueWorker('redis', 'default');
    $sync->syncQueueWorker('redis', 'default');

    Http::assertSentCount(1);
});

it('does not throw when cloud worker ingest fails', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response('Unavailable', 503),
    ]);

    app(AgentSync::class)->syncQueueWorker('sync', 'default');

    expect(true)->toBeTrue();
});

it('reports workers from the queue looping listener', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    app(SyncCloudAgent::class)->onQueueLoop(new Looping('redis', 'default'));

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/api/v1/ingest/workers'));
    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/api/v1/agent/commands'));
});

it('reports all horizon supervisors from the horizon loop listener', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 2], 202),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    $horizon = Mockery::mock(HorizonSnapshot::class);
    $horizon->shouldReceive('isAvailable')->andReturn(true);

    $this->app->instance(HorizonSnapshot::class, $horizon);

    $collector = Mockery::mock(WorkerSnapshotCollector::class);
    $collector->shouldReceive('collectFromHorizon')->once()->andReturn([
        new WorkerSnapshot('supervisor-1', 'redis:default', 'redis', 'default', 'running', 3),
        new WorkerSnapshot('supervisor-1', 'redis:billing', 'redis', 'billing', 'paused', 2, paused: true),
    ]);
    $collector->shouldReceive('collectWorkloadFromHorizon')->once()->andReturn([]);

    $this->app->instance(WorkerSnapshotCollector::class, $collector);
    $this->app->forgetInstance(AgentSync::class);

    app(SyncCloudAgent::class)->onHorizonLoop((object) ['master' => (object) ['name' => 'host']]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/v1/ingest/workers')
            && count($request->data()['workers'] ?? []) === 2;
    });
});

it('falls back to the default queue worker when horizon returns no supervisors', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => []]),
    ]);

    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.queue', 'default');

    $horizon = Mockery::mock(HorizonSnapshot::class);
    $horizon->shouldReceive('isAvailable')->andReturn(true);
    $horizon->shouldReceive('workload')->andReturn([]);

    $this->app->instance(HorizonSnapshot::class, $horizon);

    $collector = Mockery::mock(WorkerSnapshotCollector::class);
    $collector->shouldReceive('collectFromHorizon')->once()->andReturn([]);
    $collector->shouldReceive('collectFallbackQueueWorkers')->once()->andReturn([
        new WorkerSnapshot('default', 'redis:default', 'redis', 'default', 'running', 1),
    ]);
    $collector->shouldReceive('collectWorkloadFromHorizon')->once()->andReturn([]);

    $this->app->instance(WorkerSnapshotCollector::class, $collector);
    $this->app->forgetInstance(AgentSync::class);

    expect(app(AgentSync::class)->report(force: true))->toBeTrue();

    Http::assertSent(fn ($request) => ($request->data()['workers'][0]['supervisor'] ?? null) === 'default');
});

it('maps paused horizon supervisors in collector snapshots', function () {
    $collector = app(WorkerSnapshotCollector::class);

    $snapshots = $collector->fromSupervisors([
        (object) [
            'name' => 'supervisor-1',
            'pid' => 50,
            'status' => 'paused',
            'processes' => ['redis:default' => 2],
            'options' => ['connection' => 'redis', 'balance' => 'simple', 'memory' => 128],
        ],
    ]);

    expect($snapshots[0]->status)->toBe('paused')
        ->and($snapshots[0]->paused)->toBeTrue()
        ->and($snapshots[0]->balance)->toBe('simple');
});
