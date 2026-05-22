<?php

namespace Deck\Deck\Listeners;

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Support\DeckResilience;
use Illuminate\Queue\Events\Looping;

class SyncCloudAgent
{
    public function __construct(
        private readonly AgentSync $sync,
    ) {}

    public function onHorizonLoop(object $event): void
    {
        DeckResilience::runSilentlyVoid(fn (): mixed => $this->sync->syncHorizon());
    }

    public function onQueueLoop(Looping $event): void
    {
        DeckResilience::runSilentlyVoid(
            fn (): mixed => $this->sync->syncQueueWorker(
                (string) $event->connectionName,
                (string) $event->queue,
            ),
        );
    }
}
