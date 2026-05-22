<?php

namespace Deck\Deck\Listeners;

use Deck\Deck\Cloud\Events\CloudEventBuffer;
use Deck\Deck\Core\DeckResilience;
use Illuminate\Queue\Events\JobAttempted;

class FlushDeckCloudEvents
{
    public function __construct(
        private readonly CloudEventBuffer $buffer,
    ) {}

    public function handle(JobAttempted $event): void
    {
        if (in_array($event->connectionName, ['sync', 'deferred'], true)) {
            return;
        }

        DeckResilience::runSilentlyVoid(fn () => $this->buffer->flush());
    }
}
