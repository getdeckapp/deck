<?php

namespace Deck\Deck\Cloud;

readonly class QueueWorkloadSnapshot
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $length,
        public float $waitSeconds,
        public int $processes,
        public ?string $reportedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $identity = DeckCloud::installationIdentity();

        return [
            'project' => $identity['project'],
            'environment' => $identity['environment'],
            'reported_at' => $this->reportedAt ?? now()->utc()->toIso8601String(),
            'connection' => $this->connection,
            'queue' => $this->queue,
            'length' => max(0, min(10_000_000, $this->length)),
            'wait_seconds' => max(0, min(86_400, $this->waitSeconds)),
            'processes' => max(0, min(1000, $this->processes)),
        ];
    }
}
