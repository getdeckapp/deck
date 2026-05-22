<?php

use Deck\Deck\Cloud\Workers\WorkerSnapshot;
use Deck\Deck\Cloud\Workers\WorkerSnapshotCollector;

it('serializes a worker snapshot for deck cloud ingest', function () {
    config()->set('deck.project', 'Billing API');
    config()->set('deck.environment', 'Production');

    $payload = (new WorkerSnapshot(
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
        pid: 12345,
        paused: false,
        meta: [
            'horizon_version' => '5.x',
            'driver' => 'redis',
        ],
        reportedAt: '2026-05-19T12:00:00Z',
    ))->toArray();

    expect($payload)->toMatchArray([
        'project' => 'billing-api',
        'environment' => 'production',
        'reported_at' => '2026-05-19T12:00:00Z',
        'supervisor' => 'supervisor-1',
        'name' => 'redis:default',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => 'running',
        'processes' => 3,
        'balance' => 'auto',
        'memory_mb' => 128,
        'jobs_per_minute' => 42,
        'hostname' => 'worker-01.test',
        'pid' => 12345,
        'paused' => false,
        'meta' => [
            'horizon_version' => '5.x',
            'driver' => 'redis',
        ],
    ]);
});

it('maps zero processes to stopped status in collector output', function () {
    $collector = app(WorkerSnapshotCollector::class);

    $snapshots = $collector->fromSupervisors([
        (object) [
            'name' => 'supervisor-1',
            'pid' => 99,
            'status' => 'running',
            'processes' => ['redis:billing' => 0],
            'options' => ['connection' => 'redis', 'balance' => 'auto', 'memory' => 256],
        ],
    ]);

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]->status)->toBe('stopped')
        ->and($snapshots[0]->processes)->toBe(0);
});
