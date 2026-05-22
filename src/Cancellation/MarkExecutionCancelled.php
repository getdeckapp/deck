<?php

namespace Deck\Deck\Cancellation;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Core\Concerns\RunsSilently;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Recording\QueuedJobMetadata;

class MarkExecutionCancelled
{
    use RunsSilently;

    public static function mark(JobExecution $execution): void
    {
        static::runSilentlyVoid(function () use ($execution): void {
            static::markUnchecked($execution);
        });
    }

    private static function markUnchecked(JobExecution $execution): void
    {
        $finishedAt = now();
        $startedAt = $execution->started_at ?? $finishedAt;
        $durationMs = (int) $startedAt->diffInMilliseconds($finishedAt);

        app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
            metadata: new QueuedJobMetadata(
                uuid: $execution->uuid,
                jobClass: $execution->job_class,
                connection: $execution->connection,
                queue: $execution->queue,
                attempt: $execution->attempt,
                tags: $execution->tags,
            ),
            project: $execution->project,
            environment: $execution->environment,
            status: JobExecutionStatus::Cancelled,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            tags: $execution->tags,
        ));
    }
}
