<?php

use Deck\Deck\Data\UnprocessedQueue;
use Deck\Deck\Horizon\HorizonSnapshot;
use Deck\Deck\Presentation\UnprocessedQueueDetector;

it('shows unprocessed queues warning on the dashboard', function () {
    $queue = new UnprocessedQueue(
        connection: 'redis',
        queue: 'notifications',
        queueKey: 'redis:notifications',
        pending: 42,
        workerProcesses: 0,
        horizonStatus: 'running',
        suggestion: 'Assign workers to redis:notifications in config/horizon.php.',
    );

    $detector = Mockery::mock(UnprocessedQueueDetector::class);
    $detector->shouldReceive('detect')->andReturn(collect([$queue]));

    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(false);
    $snapshot->shouldReceive('summary')->andReturn(null);

    $this->app->instance(UnprocessedQueueDetector::class, $detector);
    $this->app->instance(HorizonSnapshot::class, $snapshot);

    $response = $this->get(route('deck.index'));

    $response->assertOk();
    $response->assertSee('Queues without workers');
    $response->assertSee('notifications');
    $response->assertSee('42 pending');
});
