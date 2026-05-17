<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use TorMorten\Deck\Data\UnprocessedQueue;

class UnprocessedQueueDetector
{
    public function __construct(
        private readonly HorizonSnapshot $horizon,
        private readonly QueueFactory $queues,
    ) {}

    /**
     * @return Collection<int, UnprocessedQueue>
     */
    public function detect(): Collection
    {
        if (! config('deck.unprocessed_queues.enabled', true)) {
            return collect();
        }

        if (! $this->horizon->isAvailable()) {
            return collect();
        }

        $summary = $this->horizon->summary();

        if ($summary === null) {
            return collect();
        }

        $minPending = max(1, (int) config('deck.unprocessed_queues.min_pending', 1));
        $processMap = $this->processMapFromSupervisors($this->horizon->supervisors());
        $horizonStatus = (string) $summary['status'];

        return $this->candidateQueueKeys($processMap)
            ->map(fn (string $queueKey): ?UnprocessedQueue => $this->assessQueue(
                $queueKey,
                $processMap,
                $horizonStatus,
                $minPending,
            ))
            ->filter()
            ->sortByDesc(fn (UnprocessedQueue $queue): int => $queue->pending)
            ->values();
    }

    /**
     * @param  list<array{name: string, master: string, status: string, pid: string|int|null, processes: int, queues: list<array{name: string, processes: int}>}>  $supervisors
     * @return array<string, int>
     */
    private function processMapFromSupervisors(array $supervisors): array
    {
        $map = [];

        foreach ($supervisors as $supervisor) {
            foreach ($supervisor['queues'] as $queue) {
                $map[$queue['name']] = ($map[$queue['name']] ?? 0) + (int) $queue['processes'];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $processMap
     * @return Collection<int, string>
     */
    private function candidateQueueKeys(array $processMap): Collection
    {
        $keys = collect($processMap)->keys();

        $keys = $keys
            ->merge(array_keys(config('horizon.waits', [])))
            ->merge($this->configuredHorizonQueueKeys())
            ->merge($this->additionalQueueKeys())
            ->merge($this->workloadQueueKeys());

        return $keys
            ->filter(fn (mixed $key): bool => is_string($key) && str_contains($key, ':'))
            ->unique()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function configuredHorizonQueueKeys(): array
    {
        if (! DeckHorizon::isInstalled()) {
            return [];
        }

        $environment = (string) (config('horizon.env') ?? config('app.env'));
        $defaults = config('horizon.defaults', []);
        $environmentConfig = config("horizon.environments.{$environment}", []);

        $keys = [];

        foreach (array_merge(is_array($defaults) ? $defaults : [], is_array($environmentConfig) ? $environmentConfig : []) as $supervisor) {
            if (! is_array($supervisor)) {
                continue;
            }

            $connection = (string) ($supervisor['connection'] ?? config('queue.default'));
            $queueList = (string) ($supervisor['queue'] ?? config("queue.connections.{$connection}.queue", 'default'));

            foreach (explode(',', $queueList) as $queueName) {
                $queueName = trim($queueName);

                if ($queueName !== '') {
                    $keys[] = "{$connection}:{$queueName}";
                }
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function additionalQueueKeys(): array
    {
        $keys = config('deck.unprocessed_queues.additional_queues', []);

        return is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
    }

    /**
     * @return list<string>
     */
    private function workloadQueueKeys(): array
    {
        return collect($this->horizon->workload())
            ->flatMap(function (array $queue): array {
                if (! empty($queue['key']) && is_string($queue['key'])) {
                    return [$queue['key']];
                }

                $connection = (string) config('queue.default');

                return ["{$connection}:{$queue['name']}"];
            })
            ->all();
    }

    /**
     * @param  array<string, int>  $processMap
     */
    private function assessQueue(
        string $queueKey,
        array $processMap,
        string $horizonStatus,
        int $minPending,
    ): ?UnprocessedQueue {
        [$connection, $queueName] = array_pad(explode(':', $queueKey, 2), 2, null);

        if ($connection === null || $queueName === null || $queueName === '') {
            return null;
        }

        if ($this->shouldSkipConnection($connection)) {
            return null;
        }

        $pending = $this->pendingCount($connection, $queueName);

        if ($pending < $minPending) {
            return null;
        }

        $workerProcesses = $this->workerProcessesFor($queueKey, $processMap);

        if ($workerProcesses > 0) {
            return null;
        }

        return new UnprocessedQueue(
            connection: $connection,
            queue: $queueName,
            queueKey: $queueKey,
            pending: $pending,
            workerProcesses: $workerProcesses,
            horizonStatus: $horizonStatus,
            suggestion: $this->suggestionFor($connection, $queueName, $horizonStatus),
        );
    }

    private function shouldSkipConnection(string $connection): bool
    {
        $driver = config("queue.connections.{$connection}.driver");

        return $driver === null || $driver === 'sync';
    }

    private function pendingCount(string $connection, string $queueName): int
    {
        if (Str::contains($queueName, ',')) {
            return collect(explode(',', $queueName))
                ->map(fn (string $name): int => $this->pendingCount($connection, trim($name)))
                ->sum();
        }

        try {
            $queue = $this->queues->connection($connection);
        } catch (\Throwable $e) {
            Log::warning("Deck: could not connect to queue '{$connection}'", ['error' => $e->getMessage()]);

            return 0;
        }

        return max(0, $this->readyPendingCount($queue, $queueName));
    }

    private function readyPendingCount(Queue $queue, string $queueName): int
    {
        if (is_callable([$queue, 'readyNow'])) {
            return (int) $queue->readyNow($queueName);
        }

        return (int) $queue->size($queueName);
    }

    /**
     * @param  array<string, int>  $processMap
     */
    private function workerProcessesFor(string $queueKey, array $processMap): int
    {
        if (isset($processMap[$queueKey])) {
            return (int) $processMap[$queueKey];
        }

        [$connection, $queueName] = explode(':', $queueKey, 2);

        foreach ($processMap as $assignedKey => $processes) {
            if (! str_starts_with($assignedKey, "{$connection}:")) {
                continue;
            }

            $assignedQueues = explode(',', substr($assignedKey, strlen($connection) + 1));

            if (in_array($queueName, $assignedQueues, true)) {
                return (int) $processes;
            }
        }

        return 0;
    }

    private function suggestionFor(string $connection, string $queueName, string $horizonStatus): string
    {
        return match ($horizonStatus) {
            'inactive' => 'Start Horizon (`php artisan horizon`) so workers can process pending jobs.',
            'paused' => 'Resume Horizon — workers are paused while jobs are waiting in the queue.',
            default => "Assign workers to `{$connection}:{$queueName}` in `config/horizon.php` (supervisor `queue` option).",
        };
    }
}
