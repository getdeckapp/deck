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
        return DeckCloud::isEnabled() && (DeckCloud::workersEnabled() || DeckCloud::commandsEnabled());
    }

    public function report(): void
    {
        if ($this->throttle->shouldSync('workers', 'host')) {
            $this->workerReporter->send(
                $this->workers->collectFromHorizon(),
                $this->workers->collectWorkloadFromHorizon(),
            );
        }

        $this->pollCommands();
    }

    public function syncHorizon(): void
    {
        if ($this->throttle->shouldSync('workers', 'host')) {
            $this->workerReporter->send(
                $this->workers->collectFromHorizon(),
                $this->workers->collectWorkloadFromHorizon(),
            );
        }

        $this->pollCommands();
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
