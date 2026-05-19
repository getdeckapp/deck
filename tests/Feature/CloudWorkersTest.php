<?php

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\SyncThrottle;
use Deck\Deck\Cloud\WorkerReporter;
use Deck\Deck\Cloud\WorkerSnapshot;
use Deck\Deck\Cloud\WorkerSnapshotCollector;
use Deck\Deck\Listeners\SyncCloudAgent;
use Deck\Deck\Support\HorizonSnapshot;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('deck.cloud.enabled', true);
    config()->set('deck.cloud.url', 'https://cloud.deck.test');
    config()->set('deck.cloud.api_key', 'test-api-key');
    config()->set('deck.cloud.workers.interval_seconds', 30);
    config()->set('deck.cloud.timeout_seconds', 5);
    config()->set('deck.project', 'billing-api');
    config()->set('deck.environment', 'production');

    app(SyncThrottle::class)->reset();
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

it('reports queue workers without horizon', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 1], 202),
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response(['commands' => []]),
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
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response(['commands' => []]),
    ]);

    app(SyncCloudAgent::class)->onQueueLoop(new Looping('redis', 'default'));

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/api/v1/ingest/workers'));
    Http::assertSent(fn ($request) => $request->method() === 'GET' && str_contains($request->url(), '/api/v1/agent/commands'));
});

it('reports all horizon supervisors from the horizon loop listener', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/workers' => Http::response(['accepted' => 2], 202),
        'https://cloud.deck.test/api/v1/agent/commands*' => Http::response(['commands' => []]),
    ]);

    $horizon = Mockery::mock(HorizonSnapshot::class);
    $horizon->shouldReceive('isAvailable')->andReturn(true);

    $this->app->instance(HorizonSnapshot::class, $horizon);

    $collector = Mockery::mock(WorkerSnapshotCollector::class);
    $collector->shouldReceive('collectFromHorizon')->once()->andReturn([
        new WorkerSnapshot('supervisor-1', 'redis:default', 'redis', 'default', 'running', 3),
        new WorkerSnapshot('supervisor-1', 'redis:billing', 'redis', 'billing', 'paused', 2, paused: true),
    ]);

    $this->app->instance(WorkerSnapshotCollector::class, $collector);
    $this->app->forgetInstance(AgentSync::class);

    app(SyncCloudAgent::class)->onHorizonLoop((object) ['master' => (object) ['name' => 'host']]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/v1/ingest/workers')
            && count($request->data()['workers'] ?? []) === 2;
    });
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
