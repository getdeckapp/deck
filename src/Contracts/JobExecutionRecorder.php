<?php

namespace TorMorten\Deck\Contracts;

use TorMorten\Deck\Data\JobExecutionRecord;
use TorMorten\Deck\Recorders\DatabaseJobExecutionRecorder;

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
