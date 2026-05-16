<?php

namespace TorMorten\Deck\Support;

use Illuminate\Queue\Jobs\InspectedJob;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;

class PendingJobCancellation
{
    public static function cancel(string $uuid, ?string $connection = null, ?string $queue = null): PendingCancelResult
    {
        JobCancellation::cancel($uuid);

        $queueConnection = Queue::connection($connection ?? (string) config('queue.default'));

        if (! $queueConnection instanceof RedisQueue) {
            return new PendingCancelResult(
                cancelFlagSet: true,
                removedFromQueue: false,
                message: 'Cancellation flag set. This queue driver does not support removing pending Redis payloads — the job will stop if it starts with the Cancellable middleware.',
            );
        }

        $removed = static::removeFromRedisQueue($queueConnection, $uuid, $queue);

        if ($removed) {
            return new PendingCancelResult(
                cancelFlagSet: true,
                removedFromQueue: true,
                message: 'Cancellation flag set and the job was removed from the Redis queue (best effort).',
            );
        }

        return new PendingCancelResult(
            cancelFlagSet: true,
            removedFromQueue: false,
            message: 'Cancellation flag set. No matching pending job was found in Redis — it may already be running or finished.',
        );
    }

    private static function removeFromRedisQueue(RedisQueue $redisQueue, string $uuid, ?string $queue): bool
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
