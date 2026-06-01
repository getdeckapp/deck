<?php

use Deck\Deck\Presentation\QueueAdmin;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;

it('rejects clearing when queue admin is disabled', function () {
    config()->set('deck.queue_admin.enabled', false);

    $result = QueueAdmin::clear('redis', 'default');

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('disabled');
});

it('rejects clearing non-redis connections', function () {
    config()->set('deck.queue_admin.enabled', true);
    config()->set('queue.connections.sync', ['driver' => 'sync']);

    $result = QueueAdmin::clear('sync', 'default');

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Redis');
});

it('clears a redis queue when supported', function () {
    config()->set('deck.queue_admin.enabled', true);
    config()->set('queue.connections.redis', ['driver' => 'redis']);

    $queue = Mockery::mock(RedisQueue::class);
    $queue->shouldReceive('clear')->once()->with('billing');

    Queue::shouldReceive('connection')->with('redis')->andReturn($queue);

    $result = QueueAdmin::clear('redis', 'billing');

    expect($result->success)->toBeTrue()
        ->and($result->message)->toContain('redis:billing');
});

it('parses queue keys with and without a connection prefix', function () {
    config()->set('queue.default', 'redis');

    expect(QueueAdmin::parseQueueKey('redis:emails'))->toBe([
        'connection' => 'redis',
        'queue' => 'emails',
    ])->and(QueueAdmin::parseQueueKey('default'))->toBe([
        'connection' => 'redis',
        'queue' => 'default',
    ]);
});
