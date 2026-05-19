<?php

namespace Deck\Deck\Listeners;

use Deck\Deck\Cloud\AgentSync;
use Illuminate\Queue\Events\Looping;

class SyncCloudAgent
{
    public function __construct(
        private readonly AgentSync $sync,
    ) {}

    public function onHorizonLoop(object $event): void
    {
        $this->sync->syncHorizon();
    }

    public function onQueueLoop(Looping $event): void
    {
        $this->sync->syncQueueWorker(
            (string) $event->connectionName,
            (string) $event->queue,
        );
    }
}
