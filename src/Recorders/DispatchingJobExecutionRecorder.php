<?php

namespace Deck\Deck\Recorders;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Events\JobExecutionRecorded;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The producer-facing recorder. Translates a record() call into a
 * JobExecutionRecorded event, which the database and cloud sinks consume.
 */
class DispatchingJobExecutionRecorder implements JobExecutionRecorder
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function record(JobExecutionRecord $record): void
    {
        $this->events->dispatch(new JobExecutionRecorded($record));
    }
}
