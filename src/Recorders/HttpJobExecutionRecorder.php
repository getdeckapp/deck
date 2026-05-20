<?php

namespace Deck\Deck\Recorders;

use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\HttpClient;
use Deck\Deck\Cloud\JobExecutionIngestPayload;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Support\DeckResilience;

class HttpJobExecutionRecorder implements JobExecutionRecorder
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function record(JobExecutionRecord $record): void
    {
        if (! DeckCloud::eventsEnabled()) {
            return;
        }

        DeckResilience::runSilentlyVoid(function () use ($record): void {
            $this->http->post(DeckCloud::EventsIngestPath, [
                'events' => [
                    JobExecutionIngestPayload::fromRecord($record),
                ],
            ]);
        });
    }
}
