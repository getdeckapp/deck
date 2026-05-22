<?php

namespace Deck\Deck\Recorders;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Core\DeckResilience;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobClassStat;
use Deck\Deck\Models\JobExecution;

class DatabaseJobExecutionRecorder implements JobExecutionRecorder
{
    public function record(JobExecutionRecord $record): void
    {
        DeckResilience::runSilentlyVoid(function () use ($record): void {
            $metadata = $record->metadata;

            JobExecution::query()->updateOrCreate(
                [
                    'uuid' => $metadata->uuid,
                    'attempt' => $metadata->attempt,
                ],
                $this->executionAttributes($record),
            );

            $this->updateClassStats($record);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function executionAttributes(JobExecutionRecord $record): array
    {
        $metadata = $record->metadata;

        $shared = [
            'project' => $record->project,
            'environment' => $record->environment,
            'job_class' => $metadata->jobClass,
            'connection' => $metadata->connection,
            'queue' => $metadata->queue,
            'status' => $record->status,
            'tags' => $record->tags ?? $metadata->tags,
            'started_at' => $record->startedAt,
            'created_at' => $record->startedAt,
        ];

        return match ($record->status) {
            JobExecutionStatus::Running => $shared + [
                'finished_at' => null,
                'duration_ms' => null,
            ],
            JobExecutionStatus::Blocked => $shared + [
                'finished_at' => $record->finishedAt,
                'duration_ms' => $record->durationMs ?? 0,
            ],
            JobExecutionStatus::Failed => $shared + [
                'finished_at' => $record->finishedAt,
                'duration_ms' => $record->durationMs,
                'exception_class' => $record->exceptionClass,
                'exception_message' => $record->exceptionMessage,
                'exception_trace' => $record->exceptionTrace,
                'context' => $record->context,
            ],
            default => $shared + [
                'finished_at' => $record->finishedAt,
                'duration_ms' => $record->durationMs,
                'exception_class' => null,
                'exception_message' => null,
                'exception_trace' => null,
                'context' => $record->context,
            ],
        };
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

        JobClassStat::query()->updateOrCreate($keys, $attributes);

        match ($record->status) {
            JobExecutionStatus::Completed => JobClassStat::query()->where($keys)->increment('success_count'),
            JobExecutionStatus::Failed => JobClassStat::query()->where($keys)->increment('failure_count'),
            default => null,
        };
    }
}
