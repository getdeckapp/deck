<?php

namespace Deck\Deck\Events;

use Deck\Deck\Data\JobExecutionRecord;

/**
 * Dispatched whenever a job execution lifecycle transition is recorded.
 *
 * Sinks (database, Deck Cloud) subscribe to this event rather than being
 * invoked directly, so any number of recorders can observe a transition
 * without the producer knowing about them.
 */
class JobExecutionRecorded
{
    public function __construct(
        public readonly JobExecutionRecord $record,
    ) {}
}
