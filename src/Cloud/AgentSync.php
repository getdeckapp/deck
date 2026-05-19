<?php

namespace Deck\Deck\Cloud;

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
        return DeckCloud::workersEnabled();
    }

    public function report(): void
    {
        if (! $this->throttle->shouldSync('workers', 'host')) {
            return;
        }

        $this->workerReporter->send($this->workers->collectFromHorizon());

        $this->syncCommands();
    }

    public function syncHorizon(): void
    {
        if (! $this->throttle->shouldSync('workers', 'host')) {
            return;
        }

        $this->workerReporter->send($this->workers->collectFromHorizon());

        $this->syncCommands();
    }

    public function syncQueueWorker(string $connection, string $queue): void
    {
        if (! $this->throttle->shouldSync('workers', "{$connection}:{$queue}")) {
            return;
        }

        $this->workerReporter->send($this->workers->collectFromQueueWorker($connection, $queue));

        $this->syncCommands();
    }

    public function syncCommands(): void
    {
        if (! DeckCloud::commandsEnabled() || ! $this->throttle->shouldSync('commands', 'installation')) {
            return;
        }

        $this->commands->poll();
    }
}
