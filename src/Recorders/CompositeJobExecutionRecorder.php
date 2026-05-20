<?php

namespace Deck\Deck\Recorders;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;

class CompositeJobExecutionRecorder implements JobExecutionRecorder
{
    public function __construct(
        private readonly DatabaseJobExecutionRecorder $database,
        private readonly HttpJobExecutionRecorder $http,
    ) {}

    public function record(JobExecutionRecord $record): void
    {
        $this->database->record($record);
        $this->http->record($record);
    }
}
