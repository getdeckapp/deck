<?php

namespace Deck\Deck\Presentation;

use Deck\Deck\Data\ClearQueueResult;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Queue;

class QueueAdmin
{
    public static function clear(string $connection, string $queue): ClearQueueResult
    {
        if (! config('deck.queue_admin.enabled', true)) {
            return new ClearQueueResult(
                success: false,
                message: 'Queue administration is disabled in config.',
            );
        }

        $allowed = config('deck.queue_admin.allowed_connections');

        if (is_array($allowed) && $allowed !== [] && ! in_array($connection, $allowed, true)) {
            return new ClearQueueResult(
                success: false,
                message: "Connection [{$connection}] is not allowed for queue clearing.",
            );
        }

        $driver = config("queue.connections.{$connection}.driver");

        if ($driver !== 'redis') {
            return new ClearQueueResult(
                success: false,
                message: 'Only Redis queue connections can be cleared from Deck.',
            );
        }

        $queueConnection = Queue::connection($connection);

        if (! $queueConnection instanceof RedisQueue) {
            return new ClearQueueResult(
                success: false,
                message: 'The queue connection is not a Redis queue.',
            );
        }

        if ($queue === '') {
            return new ClearQueueResult(
                success: false,
                message: 'A queue name is required.',
            );
        }

        $queueConnection->clear($queue);

        return new ClearQueueResult(
            success: true,
            message: "Cleared pending jobs from {$connection}:{$queue}. Reserved and in-flight jobs are not removed.",
            connection: $connection,
            queue: $queue,
        );
    }

    /**
     * @return array{connection: string, queue: string}
     */
    public static function parseQueueKey(string $queueKey): array
    {
        if (str_contains($queueKey, ':')) {
            [$connection, $queue] = explode(':', $queueKey, 2);

            return [
                'connection' => $connection,
                'queue' => $queue,
            ];
        }

        return [
            'connection' => (string) config('queue.default'),
            'queue' => $queueKey,
        ];
    }
}
