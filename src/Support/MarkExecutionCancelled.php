<?php

namespace TorMorten\Deck\Support;

use TorMorten\Deck\Contracts\JobExecutionRecorder;
use TorMorten\Deck\Data\JobExecutionRecord;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobExecution;

class MarkExecutionCancelled
{
    public static function mark(JobExecution $execution): void
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
