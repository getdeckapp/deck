<?php

namespace TorMorten\Deck\Support;

use TorMorten\Deck\Contracts\JobExecutionRecorder;
use TorMorten\Deck\Data\JobExecutionRecord;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobExecution;

class BlockedJobExecutionRecorder
{
    public static function record(QueuedJobMetadata $metadata): void
    {
        DeferDeckSideEffects::run(fn () => static::recordNow($metadata));
    }

    public static function recordNow(QueuedJobMetadata $metadata): void
    {
        $alreadyRecorded = JobExecution::query()
            ->where('uuid', $metadata->uuid)
            ->where('attempt', $metadata->attempt)
            ->where('status', JobExecutionStatus::Blocked)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $now = now();

        app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
            metadata: $metadata,
            project: DeckInstallation::project(),
            environment: DeckInstallation::environment(),
            status: JobExecutionStatus::Blocked,
            startedAt: $now,
            finishedAt: $now,
            durationMs: 0,
            tags: $metadata->tags,
        ));
    }
}
