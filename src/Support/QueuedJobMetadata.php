<?php

namespace TorMorten\Deck\Support;

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

    public static function fromQueueJob(QueueJobContract $job): self
    {
        $payload = method_exists($job, 'payload') ? $job->payload() : [];

        $uuid = method_exists($job, 'uuid')
            ? $job->uuid()
            : ($payload['uuid'] ?? (string) Str::uuid());

        $tags = $payload['tags'] ?? null;

        return new self(
            uuid: $uuid,
            jobClass: method_exists($job, 'resolveQueuedJobClass')
                ? $job->resolveQueuedJobClass()
                : $job->resolveName(),
            connection: $job->getConnectionName(),
            queue: $job->getQueue() ?? 'default',
            attempt: $job->attempts(),
            tags: is_array($tags) ? array_values(array_map('strval', $tags)) : null,
        );
    }
}
