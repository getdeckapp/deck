<?php

namespace Deck\Deck\Cloud;

use Composer\InstalledVersions;
use Deck\Deck\Support\DeckHorizon;
use Deck\Deck\Support\HorizonSnapshot;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

class WorkerSnapshotCollector
{
    public function __construct(
        private readonly HorizonSnapshot $horizon,
    ) {}

    /**
     * @return list<WorkerSnapshot>
     */
    public function collectFromHorizon(): array
    {
        if (! $this->horizon->isAvailable() || ! class_exists(SupervisorRepository::class)) {
            return [];
        }

        return $this->fromSupervisors(app(SupervisorRepository::class)->all());
    }

    /**
     * @return list<WorkerSnapshot>
     */
    public function collectFromQueueWorker(string $connection, string $queue): array
    {
        return [
            $this->makeSnapshot(
                supervisor: 'default',
                connection: $connection,
                queue: $queue,
                horizonStatus: 'running',
                processes: 1,
                hostname: $this->hostname(),
                pid: $this->pid(),
                paused: false,
                meta: $this->metaForConnection($connection),
            ),
        ];
    }

    /**
     * @param  list<object>  $supervisors
     * @return list<WorkerSnapshot>
     */
    public function fromSupervisors(array $supervisors): array
    {
        $metrics = class_exists(MetricsRepository::class)
            ? app(MetricsRepository::class)
            : null;

        $snapshots = [];

        foreach ($supervisors as $supervisor) {
            $processMap = is_array($supervisor->processes) ? $supervisor->processes : [];
            $options = is_array($supervisor->options) ? $supervisor->options : [];
            $horizonStatus = (string) ($supervisor->status ?? 'stopped');
            $balance = isset($options['balance']) ? (string) $options['balance'] : null;
            $memoryMb = isset($options['memory']) ? (int) $options['memory'] : null;
            $hostname = $this->hostname();
            $pid = isset($supervisor->pid) ? (int) $supervisor->pid : null;
            $paused = $horizonStatus === 'paused';

            if ($processMap === []) {
                $connection = (string) ($options['connection'] ?? 'redis');
                $queue = (string) ($options['queue'] ?? 'default');

                $snapshots[] = $this->makeSnapshot(
                    supervisor: (string) $supervisor->name,
                    connection: $connection,
                    queue: $queue,
                    horizonStatus: $horizonStatus,
                    processes: 0,
                    balance: $balance,
                    memoryMb: $memoryMb,
                    jobsPerMinute: $this->jobsPerMinuteForQueue($metrics, $connection.':'.$queue),
                    hostname: $hostname,
                    pid: $pid,
                    paused: $paused,
                    meta: $this->metaForConnection($connection),
                );

                continue;
            }

            foreach ($processMap as $queueKey => $processCount) {
                [$connection, $queue] = $this->parseQueueKey((string) $queueKey);

                $snapshots[] = $this->makeSnapshot(
                    supervisor: (string) $supervisor->name,
                    connection: $connection,
                    queue: $queue,
                    horizonStatus: $horizonStatus,
                    processes: (int) $processCount,
                    balance: $balance,
                    memoryMb: $memoryMb,
                    jobsPerMinute: $this->jobsPerMinuteForQueue($metrics, (string) $queueKey),
                    hostname: $hostname,
                    pid: $pid,
                    paused: $paused,
                    meta: $this->metaForConnection($connection),
                );
            }
        }

        return $snapshots;
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $meta
     */
    private function makeSnapshot(
        string $supervisor,
        string $connection,
        string $queue,
        string $horizonStatus,
        int $processes,
        ?string $balance = null,
        ?int $memoryMb = null,
        ?int $jobsPerMinute = null,
        ?string $hostname = null,
        ?int $pid = null,
        ?bool $paused = null,
        ?array $meta = null,
    ): WorkerSnapshot {
        $status = $this->mapStatus($horizonStatus, $processes);

        return new WorkerSnapshot(
            supervisor: $supervisor,
            name: $connection.':'.$queue,
            connection: $connection,
            queue: $queue,
            status: $status,
            processes: $processes,
            balance: $balance !== null && $balance !== '' && $balance !== 'off' ? $balance : null,
            memoryMb: $memoryMb,
            jobsPerMinute: $jobsPerMinute,
            hostname: $hostname,
            pid: $pid !== null && $pid >= 1 ? $pid : null,
            paused: $paused ?? $status === 'paused',
            meta: $meta,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseQueueKey(string $queueKey): array
    {
        if (! str_contains($queueKey, ':')) {
            return ['redis', $queueKey !== '' ? $queueKey : 'default'];
        }

        [$connection, $queue] = explode(':', $queueKey, 2);

        return [
            $connection !== '' ? $connection : 'redis',
            $queue !== '' ? $queue : 'default',
        ];
    }

    private function mapStatus(string $horizonStatus, int $processes): string
    {
        if ($processes === 0) {
            return 'stopped';
        }

        if ($horizonStatus === 'paused') {
            return 'paused';
        }

        return 'running';
    }

    private function jobsPerMinuteForQueue(?MetricsRepository $metrics, string $queueKey): ?int
    {
        if ($metrics === null) {
            return null;
        }

        try {
            $throughput = (int) $metrics->throughputForQueue($queueKey);

            return $throughput > 0 ? $throughput : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    private function metaForConnection(string $connection): array
    {
        $meta = [];

        if (DeckHorizon::isInstalled()) {
            $version = InstalledVersions::getPrettyVersion('laravel/horizon');

            if (is_string($version) && $version !== '') {
                $meta['horizon_version'] = $version;
            }
        }

        $driver = config('queue.connections.'.$connection.'.driver');

        if (is_string($driver) && $driver !== '') {
            $meta['driver'] = $driver;
        }

        return $meta;
    }

    private function hostname(): ?string
    {
        $host = gethostname();

        return is_string($host) && $host !== '' ? $host : null;
    }

    private function pid(): ?int
    {
        $pid = getmypid();

        return is_int($pid) && $pid >= 1 ? $pid : null;
    }
}
