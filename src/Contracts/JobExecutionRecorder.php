<?php

namespace Deck\Deck\Contracts;

use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Recorders\DatabaseJobExecutionRecorder;

/**
 * Persists job execution lifecycle events.
 *
 * @see DatabaseJobExecutionRecorder Self-hosted default
 * @see IMPLEMENTATION.md Deck Cloud — future HttpJobExecutionRecorder
 */
interface JobExecutionRecorder
{
    public function record(JobExecutionRecord $record): void;
}
