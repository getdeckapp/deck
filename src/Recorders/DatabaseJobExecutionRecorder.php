<?php

namespace TorMorten\Deck\Recorders;

use TorMorten\Deck\Contracts\JobExecutionRecorder;
use TorMorten\Deck\Data\JobExecutionRecord;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;

class DatabaseJobExecutionRecorder implements JobExecutionRecorder
{
    public function record(JobExecutionRecord $record): void
    {
        $metadata = $record->metadata;

        JobExecution::query()->updateOrCreate(
            [
                'uuid' => $metadata->uuid,
                'attempt' => $metadata->attempt,
            ],
            [
                'project' => $record->project,
                'environment' => $record->environment,
                'job_class' => $metadata->jobClass,
                'connection' => $metadata->connection,
                'queue' => $metadata->queue,
                'status' => $record->status,
                'tags' => $record->tags ?? $metadata->tags,
                'started_at' => $record->startedAt,
                'finished_at' => $record->finishedAt,
                'duration_ms' => $record->durationMs,
                'exception_class' => $record->exceptionClass,
                'exception_message' => $record->exceptionMessage,
                'context' => $record->context,
            ],
        );

        $this->updateClassStats($record);
    }

    private function updateClassStats(JobExecutionRecord $record): void
    {
        $metadata = $record->metadata;

        $keys = [
            'project' => $record->project,
            'environment' => $record->environment,
            'job_class' => $metadata->jobClass,
        ];

        $attributes = match ($record->status) {
            JobExecutionStatus::Running => [
                'last_started_at' => $record->startedAt,
                'last_status' => $record->status,
                'last_uuid' => $metadata->uuid,
            ],
            default => [
                'last_started_at' => $record->startedAt,
                'last_finished_at' => $record->finishedAt,
                'last_status' => $record->status,
                'last_duration_ms' => $record->durationMs,
                'last_uuid' => $metadata->uuid,
            ],
        };

        $stat = JobClassStat::query()->updateOrCreate($keys, $attributes);

        match ($record->status) {
            JobExecutionStatus::Completed => $stat->increment('success_count'),
            JobExecutionStatus::Failed => $stat->increment('failure_count'),
            default => null,
        };
    }
}
