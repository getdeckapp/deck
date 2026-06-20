<?php

namespace Deck\Deck\Blocking;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Core\Concerns\RunsSilently;
use Deck\Deck\Core\DeckInstallation;
use Deck\Deck\Core\DeferDeckSideEffects;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Recording\JobExecutionTiming;
use Deck\Deck\Recording\QueuedJobMetadata;

class BlockedJobExecutionRecorder
{
    use RunsSilently;

    public static function record(QueuedJobMetadata $metadata): void
    {
        DeferDeckSideEffects::run(fn () => static::recordNow($metadata));
    }

    public static function recordNow(QueuedJobMetadata $metadata): void
    {
        static::runSilentlyVoid(function () use ($metadata): void {
            static::recordNowUnchecked($metadata);
        });
    }

    private static function recordNowUnchecked(QueuedJobMetadata $metadata): void
    {
        if (JobExecutionTiming::peek($metadata->uuid, $metadata->attempt)?->isBlocked()) {
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
            waitMs: 0,
            tags: $metadata->tags,
        ));

        JobExecutionTiming::markBlocked($metadata->uuid, $metadata->attempt);
    }
}
