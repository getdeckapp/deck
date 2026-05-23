<?php

namespace Deck\Deck\Cloud\Workers;

use Composer\InstalledVersions;
use Deck\Deck\Horizon\DeckHorizon;
use Deck\Deck\Horizon\HorizonSnapshot;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

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
        if (! DeckHorizon::isInstalled() || ! interface_exists(SupervisorRepository::class)) {
            return [];
        }

        $repository = app(SupervisorRepository::class);
        $snapshots = $this->fromSupervisors($repository->all());

        if ($snapshots !== []) {
            return $snapshots;
        }

        return $this->fromSupervisorNames($repository->names());
    }

    /**
     * Horizon lists supervisor names in Redis before hashes are readable, or after
     * hashes expire. Emit minimal running snapshots so Deck Cloud still gets heartbeats.
     *
     * @param  list<string>  $names
     * @return list<WorkerSnapshot>
     */
    public function fromSupervisorNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $repository = app(SupervisorRepository::class);
        $snapshots = [];

        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $supervisor = $repository->find($name);

            if ($supervisor !== null) {
                $snapshots = array_merge($snapshots, $this->fromSupervisors([$supervisor]));

                continue;
            }

            $connection = (string) config('queue.default', 'redis');

            $snapshots[] = $this->makeSnapshot(
                supervisor: $name,
                connection: $connection !== '' ? $connection : 'redis',
                queue: $this->defaultQueueName(),
                horizonStatus: 'running',
                processes: 1,
                hostname: $this->hostname(),
                paused: false,
                meta: $this->metaForConnection($connection),
            );
        }

        return $snapshots;
    }

    private function defaultQueueName(): string
    {
        $connection = (string) config('queue.default', 'redis');
        $queue = config("queue.connections.{$connection}.queue", 'default');

        if (is_array($queue)) {
            $queue = $queue[0] ?? 'default';
        }

        return (string) $queue !== '' ? (string) $queue : 'default';
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
     * @return list<WorkerSnapshot>
     */
    public function collectFallbackQueueWorkers(): array
    {
        $connection = (string) config('queue.default', 'redis');

        if ($connection === '' || $connection === 'sync') {
            return [];
        }

        $queue = config("queue.connections.{$connection}.queue", 'default');

        if (is_array($queue)) {
            $queue = $queue[0] ?? 'default';
        }

        return $this->collectFromQueueWorker($connection, (string) $queue);
    }

    /**
     * @return list<QueueWorkloadSnapshot>
     */
    public function collectWorkloadFromHorizon(): array
    {
        if (! DeckHorizon::isInstalled() || ! interface_exists(WorkloadRepository::class)) {
            return [];
        }

        return collect(app(WorkloadRepository::class)->get())
            ->map(function (array $queue): QueueWorkloadSnapshot {
                [$connection, $name] = $this->parseQueueKey((string) $queue['name']);

                return new QueueWorkloadSnapshot(
                    connection: $connection,
                    queue: $name,
                    length: (int) ($queue['length'] ?? 0),
                    waitSeconds: (float) ($queue['wait'] ?? 0),
                    processes: (int) ($queue['processes'] ?? 0),
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<object>  $supervisors
     * @return list<WorkerSnapshot>
     */
    public function fromSupervisors(array $supervisors): array
    {
        $metrics = interface_exists(MetricsRepository::class) && app()->bound(MetricsRepository::class)
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
            try {
                $version = InstalledVersions::getPrettyVersion('laravel/horizon');

                if (is_string($version) && $version !== '') {
                    $meta['horizon_version'] = $version;
                }
            } catch (\OutOfBoundsException) {
                // Horizon is bound in the container but not listed in this app's composer.lock.
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
