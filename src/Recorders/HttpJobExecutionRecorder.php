<?php

namespace Deck\Deck\Recorders;

use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Events\CloudEventBuffer;
use Deck\Deck\Cloud\Events\JobExecutionIngestPayload;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Core\DeckResilience;
use Deck\Deck\Data\JobExecutionRecord;

class HttpJobExecutionRecorder implements JobExecutionRecorder
{
    public function __construct(
        private readonly CloudEventBuffer $buffer,
    ) {}

    public function record(JobExecutionRecord $record): void
    {
        if (! DeckCloud::eventsEnabled()) {
            return;
        }

        DeckResilience::runSilentlyVoid(function () use ($record): void {
            $this->buffer->push(JobExecutionIngestPayload::fromRecord($record));
        });
    }
}
