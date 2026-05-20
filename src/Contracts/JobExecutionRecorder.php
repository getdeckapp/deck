<?php

namespace Deck\Deck\Contracts;

use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Recorders\DatabaseJobExecutionRecorder;

/**
 * Persists job execution lifecycle events.
 *
 * @see DatabaseJobExecutionRecorder Self-hosted default
 * @see HttpJobExecutionRecorder Deck Cloud HTTP ingest
 * @see CompositeJobExecutionRecorder Local DB + Cloud when enabled
 */
interface JobExecutionRecorder
{
    public function record(JobExecutionRecord $record): void;
}
