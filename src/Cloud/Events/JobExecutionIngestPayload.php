<?php

namespace Deck\Deck\Cloud\Events;

use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;

class JobExecutionIngestPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRecord(JobExecutionRecord $record): array
    {
        $metadata = $record->metadata;

        $payload = [
            'project' => DeckCloud::slug($record->project),
            'environment' => DeckCloud::slug($record->environment),
            'job_class' => $metadata->jobClass,
            'uuid' => $metadata->uuid,
            'connection' => $metadata->connection,
            'queue' => $metadata->queue,
            'status' => $record->status->value,
            'attempt' => $metadata->attempt,
            'started_at' => $record->startedAt->utc()->toIso8601String(),
        ];

        if ($metadata->tags !== null && $metadata->tags !== []) {
            $payload['tags'] = $metadata->tags;
        }

        if ($record->status === JobExecutionStatus::Running) {
            return $payload;
        }

        if ($record->finishedAt !== null) {
            $payload['finished_at'] = $record->finishedAt->utc()->toIso8601String();
        }

        if ($record->durationMs !== null) {
            $payload['duration_ms'] = max(0, $record->durationMs);
        }

        if ($record->status === JobExecutionStatus::Failed) {
            if ($record->exceptionClass !== null && $record->exceptionClass !== '') {
                $payload['exception_class'] = $record->exceptionClass;
            }

            if ($record->exceptionMessage !== null && $record->exceptionMessage !== '') {
                $payload['exception_message'] = $record->exceptionMessage;
            }

            if (static::shouldSendExceptionTrace() && $record->exceptionTrace !== null && $record->exceptionTrace !== '') {
                $payload['exception_trace'] = static::truncateTrace($record->exceptionTrace);
            }
        }

        if ($record->context !== null && $record->context !== [] && config('deck.store_context', false)) {
            $payload['context'] = $record->context;
        }

        return $payload;
    }

    private static function shouldSendExceptionTrace(): bool
    {
        return (bool) config('deck.cloud.events.send_exception_trace', true);
    }

    private static function truncateTrace(string $trace): string
    {
        $limit = max(1_024, (int) config('deck.exception_trace_bytes', 65_536));

        return mb_substr($trace, 0, $limit);
    }
}
