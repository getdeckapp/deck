<?php

namespace Deck\Deck\Support;

use Illuminate\Queue\Jobs\InspectedJob;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;

class PendingJobCancellation
{
    public static function cancel(string $uuid, ?string $connection = null, ?string $queue = null, bool $force = false): PendingCancelResult
    {
        JobCancellation::cancel($uuid);

        $queueConnection = Queue::connection($connection ?? (string) config('queue.default'));

        if (! $queueConnection instanceof RedisQueue) {
            return new PendingCancelResult(
                cancelFlagSet: true,
                removedFromQueue: false,
                removedFromReserved: false,
                message: $force
                    ? 'Cancellation flag set. Force cancel requires a Redis queue connection to remove payloads.'
                    : 'Cancellation flag set. This queue driver does not support removing pending Redis payloads — the job will stop if it uses the Cancellable middleware.',
            );
        }

        $removedFromQueue = static::removeFromRedisQueue($queueConnection, $uuid, $queue, includeReserved: false);
        $removedFromReserved = false;

        if ($force) {
            $removedFromReserved = static::removeFromRedisQueue($queueConnection, $uuid, $queue, includeReserved: true);
        }

        $removed = $removedFromQueue || $removedFromReserved;

        if ($force && $removedFromReserved) {
            return new PendingCancelResult(
                cancelFlagSet: true,
                removedFromQueue: $removedFromQueue,
                removedFromReserved: true,
                message: 'Force cancel applied: flag set and the reserved Redis payload was removed. The worker process may still finish its current step.',
            );
        }

        if ($force) {
            return new PendingCancelResult(
                cancelFlagSet: true,
                removedFromQueue: $removedFromQueue,
                removedFromReserved: false,
                message: 'Force cancel applied: flag set. No reserved payload was found — the job may still be in a worker without cooperative checks.',
            );
        }

        if ($removed) {
            return new PendingCancelResult(
                cancelFlagSet: true,
                removedFromQueue: true,
                removedFromReserved: false,
                message: 'Cancellation requested. The cancel flag is set and a pending queue payload was removed (best effort).',
            );
        }

        return new PendingCancelResult(
            cancelFlagSet: true,
            removedFromQueue: false,
            removedFromReserved: false,
            message: 'Cancellation requested. The worker will stop at the next cooperative check if the job uses the Cancellable middleware.',
        );
    }

    private static function removeFromRedisQueue(RedisQueue $redisQueue, string $uuid, ?string $queue, bool $includeReserved): bool
    {
        $redis = $redisQueue->getConnection();
        $queues = $queue !== null ? [$queue] : static::discoverQueueNames($redisQueue);
        $removed = false;

        foreach ($queues as $queueName) {
            $key = static::queueRedisKey($redisQueue, $queueName);

            foreach ($redis->lrange($key, 0, -1) as $payload) {
                if (! is_string($payload) || ! static::payloadMatchesUuid($payload, $uuid)) {
                    continue;
                }

                $redis->lrem($key, 0, $payload);
                $removed = true;
            }

            foreach ($redis->zrange($key.':delayed', 0, -1) as $payload) {
                if (! is_string($payload) || ! static::payloadMatchesUuid($payload, $uuid)) {
                    continue;
                }

                $redis->zrem($key.':delayed', $payload);
                $removed = true;
            }

            if ($includeReserved) {
                foreach ($redis->zrange($key.':reserved', 0, -1) as $payload) {
                    if (! is_string($payload) || ! static::payloadMatchesUuid($payload, $uuid)) {
                        continue;
                    }

                    $redis->zrem($key.':reserved', $payload);
                    $removed = true;
                }
            }
        }

        return $removed;
    }

    private static function payloadMatchesUuid(string $payload, string $uuid): bool
    {
        return InspectedJob::fromPayload($payload)->uuid === $uuid;
    }

    /**
     * @return list<string>
     */
    private static function discoverQueueNames(RedisQueue $queue): array
    {
        $method = new ReflectionMethod($queue, 'allQueueNames');
        $method->setAccessible(true);

        return $method->invoke($queue)->all();
    }

    private static function queueRedisKey(RedisQueue $queue, string $name): string
    {
        $method = new ReflectionMethod($queue, 'getQueueRedisKey');
        $method->setAccessible(true);

        return $method->invoke($queue, $name);
    }
}
