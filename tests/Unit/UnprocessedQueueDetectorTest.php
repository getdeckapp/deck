<?php

use Deck\Deck\Horizon\HorizonSnapshot;
use Deck\Deck\Presentation\UnprocessedQueueDetector;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\RedisQueue;

it('returns empty when horizon is unavailable', function () {
    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(false);

    $this->app->instance(HorizonSnapshot::class, $snapshot);

    expect(app(UnprocessedQueueDetector::class)->detect())->toBeEmpty();
});

it('detects queues with pending jobs and no workers', function () {
    config()->set('deck.unprocessed_queues.enabled', true);
    config()->set('queue.connections.redis', [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
    ]);
    config()->set('horizon.waits', [
        'redis:default' => 60,
        'redis:orphan' => 60,
    ]);

    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(true);
    $snapshot->shouldReceive('summary')->andReturn([
        'status' => 'running',
        'processes' => 2,
        'jobs_per_minute' => 10,
        'paused_masters' => 0,
        'wait' => [],
    ]);
    $snapshot->shouldReceive('supervisors')->andReturn([
        [
            'name' => 'host:supervisor-1',
            'master' => 'host',
            'status' => 'running',
            'pid' => 1,
            'processes' => 2,
            'queues' => [
                ['name' => 'redis:default', 'processes' => 2],
            ],
        ],
    ]);
    $snapshot->shouldReceive('workload')->andReturn([
        ['name' => 'default', 'length' => 0, 'wait' => 0, 'processes' => 2],
        ['name' => 'orphan', 'length' => 15, 'wait' => 0, 'processes' => 0],
    ]);

    $queue = Mockery::mock(RedisQueue::class);
    $queue->shouldReceive('readyNow')->with('default')->andReturn(0);
    $queue->shouldReceive('readyNow')->with('orphan')->andReturn(15);

    $queues = Mockery::mock(QueueFactory::class);
    $queues->shouldReceive('connection')->with('redis')->andReturn($queue);

    $this->app->instance(HorizonSnapshot::class, $snapshot);
    $this->app->instance(QueueFactory::class, $queues);

    $detected = app(UnprocessedQueueDetector::class)->detect();

    expect($detected)->toHaveCount(1)
        ->and($detected->first()->connection)->toBe('redis')
        ->and($detected->first()->queue)->toBe('orphan')
        ->and($detected->first()->pending)->toBe(15);
});

it('does not flag queues that have workers assigned', function () {
    config()->set('deck.unprocessed_queues.enabled', true);
    config()->set('queue.connections.redis', ['driver' => 'redis']);
    config()->set('horizon.waits', ['redis:default' => 60]);

    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(true);
    $snapshot->shouldReceive('summary')->andReturn([
        'status' => 'running',
        'processes' => 2,
        'jobs_per_minute' => 10,
        'paused_masters' => 0,
        'wait' => [],
    ]);
    $snapshot->shouldReceive('supervisors')->andReturn([
        [
            'name' => 'host:supervisor-1',
            'master' => 'host',
            'status' => 'running',
            'pid' => 1,
            'processes' => 2,
            'queues' => [
                ['name' => 'redis:default', 'processes' => 2],
            ],
        ],
    ]);
    $snapshot->shouldReceive('workload')->andReturn([]);

    $queue = Mockery::mock(RedisQueue::class);
    $queue->shouldReceive('readyNow')->with('default')->andReturn(25);

    $queues = Mockery::mock(QueueFactory::class);
    $queues->shouldReceive('connection')->with('redis')->andReturn($queue);

    $this->app->instance(HorizonSnapshot::class, $snapshot);
    $this->app->instance(QueueFactory::class, $queues);

    expect(app(UnprocessedQueueDetector::class)->detect())->toBeEmpty();
});

it('flags all waited queues when horizon is inactive', function () {
    config()->set('deck.unprocessed_queues.enabled', true);
    config()->set('queue.connections.redis', ['driver' => 'redis']);
    config()->set('horizon.waits', ['redis:default' => 60]);

    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(true);
    $snapshot->shouldReceive('summary')->andReturn([
        'status' => 'inactive',
        'processes' => 0,
        'jobs_per_minute' => 0,
        'paused_masters' => 0,
        'wait' => [],
    ]);
    $snapshot->shouldReceive('supervisors')->andReturn([]);
    $snapshot->shouldReceive('workload')->andReturn([]);

    $queue = Mockery::mock(RedisQueue::class);
    $queue->shouldReceive('readyNow')->with('default')->andReturn(3);

    $queues = Mockery::mock(QueueFactory::class);
    $queues->shouldReceive('connection')->with('redis')->andReturn($queue);

    $this->app->instance(HorizonSnapshot::class, $snapshot);
    $this->app->instance(QueueFactory::class, $queues);

    $detected = app(UnprocessedQueueDetector::class)->detect();

    expect($detected)->toHaveCount(1)
        ->and($detected->first()->horizonStatus)->toBe('inactive')
        ->and($detected->first()->suggestion)->toContain('php artisan horizon');
});
