<?php

namespace Deck\Deck\Support;

use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Support\Str;

class QueuedJobMetadata
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $jobClass,
        public readonly string $connection,
        public readonly string $queue,
        public readonly int $attempt,
        /** @var list<string>|null */
        public readonly ?array $tags,
    ) {}

    public static function fromCommand(object $command): self
    {
        $connection = isset($command->connection) && $command->connection !== null
            ? (string) $command->connection
            : (string) config('queue.default', 'sync');

        $queue = isset($command->queue) && $command->queue !== null
            ? (string) $command->queue
            : 'default';

        return new self(
            uuid: (string) Str::uuid(),
            jobClass: $command::class,
            connection: $connection,
            queue: $queue,
            attempt: 1,
            tags: static::tagsFromCommand($command),
        );
    }

    public static function fromQueueJob(QueueJobContract $job): self
    {
        $payload = $job->payload();

        $uuid = $payload['uuid'] ?? null;
        $uuid = is_string($uuid) && $uuid !== '' ? $uuid : (string) Str::uuid();

        $tags = $payload['tags'] ?? null;

        return new self(
            uuid: $uuid,
            jobClass: $job->resolveQueuedJobClass(),
            connection: $job->getConnectionName(),
            queue: $job->getQueue() !== null && $job->getQueue() !== '' ? $job->getQueue() : 'default',
            attempt: $job->attempts(),
            tags: is_array($tags) ? array_values(array_map('strval', $tags)) : null,
        );
    }

    /**
     * @return list<string>|null
     */
    private static function tagsFromCommand(object $command): ?array
    {
        if (! method_exists($command, 'tags')) {
            return null;
        }

        $tags = $command->tags();

        return is_array($tags) ? array_values(array_map('strval', $tags)) : null;
    }
}
