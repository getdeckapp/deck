<?php

namespace Deck\Deck\Cloud\Agent;

use Deck\Deck\Cloud\Commands\CommandPoller;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Workers\QueueWorkloadSnapshot;
use Deck\Deck\Cloud\Workers\WorkerReporter;
use Deck\Deck\Cloud\Workers\WorkerSnapshot;
use Deck\Deck\Cloud\Workers\WorkerSnapshotCollector;

/**
 * Pushes worker snapshots and pulls remote commands from Deck Cloud.
 */
class AgentSync
{
    public function __construct(
        private readonly WorkerSnapshotCollector $workers,
        private readonly WorkerReporter $workerReporter,
        private readonly SyncThrottle $throttle,
        private readonly CommandPoller $commands,
    ) {}

    public static function isEnabled(): bool
    {
        return DeckCloud::isEnabled() && (DeckCloud::workersEnabled() || DeckCloud::commandsEnabled());
    }

    public function report(bool $force = false): bool
    {
        return $this->reportCollected(
            $this->collectWorkerSnapshots(),
            $this->workers->collectWorkloadFromHorizon(),
            force: $force,
        );
    }

    /**
     * @param  list<WorkerSnapshot>  $workers
     * @param  list<QueueWorkloadSnapshot>  $queues
     */
    public function reportCollected(array $workers, array $queues = [], bool $force = false): bool
    {
        if ($force) {
            $this->throttle->reset();
        }

        $accepted = false;

        if ($this->throttle->shouldSync('workers', 'host')) {
            $accepted = $this->workerReporter->send($workers, $queues);
        }

        $this->pollCommands();

        return $accepted;
    }

    public function syncHorizon(): void
    {
        if ($this->throttle->shouldSync('workers', 'host')) {
            $this->workerReporter->send(
                $this->collectWorkerSnapshots(),
                $this->workers->collectWorkloadFromHorizon(),
            );
        }

        $this->pollCommands();
    }

    /**
     * @return list<\Deck\Deck\Cloud\Workers\WorkerSnapshot>
     */
    private function collectWorkerSnapshots(): array
    {
        $fromHorizon = $this->workers->collectFromHorizon();

        if ($fromHorizon !== []) {
            return $fromHorizon;
        }

        return $this->workers->collectFallbackQueueWorkers();
    }

    public function syncQueueWorker(string $connection, string $queue): void
    {
        if ($this->throttle->shouldSync('workers', "{$connection}:{$queue}")) {
            $this->workerReporter->send(
                $this->workers->collectFromQueueWorker($connection, $queue),
            );
        }

        $this->pollCommands();
    }

    public function pollCommands(): void
    {
        if (! DeckCloud::commandsEnabled() || ! $this->throttle->shouldSync('commands', 'installation')) {
            return;
        }

        $this->commands->poll();
    }
}
