<?php

namespace Deck\Deck\Support;

use Illuminate\Contracts\Queue\Job as QueueJobContract;

/**
 * Minimal queue job stub used only by deck:doctor to exercise real event listeners.
 */
class DoctorProbeQueueJob implements QueueJobContract
{
    public function __construct(
        private readonly string $uuid,
        private readonly string $jobClass,
        private readonly string $connection = 'doctor',
        private readonly string $queue = 'doctor',
        private readonly int $attempt = 1,
    ) {}

    public function uuid(): ?string
    {
        return $this->uuid;
    }

    public function getJobId(): string
    {
        return 'doctor-probe';
    }

    public function payload(): array
    {
        return [
            'uuid' => $this->uuid,
            'displayName' => $this->jobClass,
            'data' => ['commandName' => $this->jobClass],
        ];
    }

    public function fire(): void {}

    public function release($delay = 0): void {}

    public function isReleased(): bool
    {
        return false;
    }

    public function delete(): void {}

    public function isDeleted(): bool
    {
        return false;
    }

    public function isDeletedOrReleased(): bool
    {
        return true;
    }

    public function attempts(): int
    {
        return $this->attempt;
    }

    public function hasFailed(): bool
    {
        return false;
    }

    public function markAsFailed(): void {}

    public function fail($e = null): void {}

    public function maxTries(): ?int
    {
        return null;
    }

    public function maxExceptions(): ?int
    {
        return null;
    }

    public function timeout(): ?int
    {
        return null;
    }

    public function retryUntil(): ?int
    {
        return null;
    }

    public function getName(): string
    {
        return $this->jobClass;
    }

    public function resolveName(): string
    {
        return $this->jobClass;
    }

    public function resolveQueuedJobClass(): string
    {
        return $this->jobClass;
    }

    public function getConnectionName(): string
    {
        return $this->connection;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getRawBody(): string
    {
        return '';
    }
}
