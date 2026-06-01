<?php

namespace Deck\Deck\Cloud\Workers;

use Deck\Deck\Cloud\DeckCloud;
use Illuminate\Support\Str;

readonly class WorkerSnapshot
{
    /**
     * @param  array<string, bool|float|int|string|null>  $meta
     */
    public function __construct(
        public string $supervisor,
        public string $name,
        public string $connection,
        public string $queue,
        public string $status,
        public int $processes,
        public ?string $balance = null,
        public ?int $memoryMb = null,
        public ?int $jobsPerMinute = null,
        public ?string $currentJobUuid = null,
        public ?string $hostname = null,
        public ?int $pid = null,
        public ?bool $paused = null,
        public ?array $meta = null,
        public ?string $reportedAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $identity = DeckCloud::installationIdentity();

        $payload = [
            'project' => $identity['project'],
            'environment' => $identity['environment'],
            'reported_at' => $this->reportedAt ?? now()->utc()->toIso8601String(),
            'supervisor' => $this->supervisor,
            'name' => $this->name,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'status' => $this->status,
            'processes' => max(0, min(1000, $this->processes)),
        ];

        if ($this->balance !== null && $this->balance !== '') {
            $payload['balance'] = $this->balance;
        }

        if ($this->memoryMb !== null) {
            $payload['memory_mb'] = max(0, min(65_535, $this->memoryMb));
        }

        if ($this->jobsPerMinute !== null) {
            $payload['jobs_per_minute'] = max(0, min(1_000_000, $this->jobsPerMinute));
        }

        if ($this->currentJobUuid !== null && Str::isUuid($this->currentJobUuid)) {
            $payload['current_job_uuid'] = $this->currentJobUuid;
        }

        if ($this->hostname !== null && $this->hostname !== '') {
            $payload['hostname'] = $this->hostname;
        }

        if ($this->pid !== null && $this->pid >= 1) {
            $payload['pid'] = $this->pid;
        }

        if ($this->paused !== null) {
            $payload['paused'] = $this->paused;
        }

        if ($this->meta !== null && $this->meta !== []) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }
}
