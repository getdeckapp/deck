<?php

namespace Deck\Deck\Cloud\Events;

use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;

class CloudExecutionBackfillPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromExecution(JobExecution $execution): array
    {
        $payload = [
            'project' => DeckCloud::slug($execution->project),
            'environment' => DeckCloud::slug($execution->environment),
            'job_class' => $execution->job_class,
            'uuid' => $execution->uuid,
            'connection' => $execution->connection,
            'queue' => $execution->queue,
            'status' => $execution->status->value,
            'attempt' => $execution->attempt,
            'started_at' => $execution->started_at->utc()->toIso8601String(),
            ...CloudObservabilityIngestFields::fromExecution($execution),
        ];

        if ($execution->tags !== null && $execution->tags !== []) {
            $payload['tags'] = $execution->tags;
        }

        if ($execution->status === JobExecutionStatus::Running) {
            return $payload;
        }

        if ($execution->finished_at !== null) {
            $payload['finished_at'] = $execution->finished_at->utc()->toIso8601String();
        }

        if ($execution->duration_ms !== null) {
            $payload['duration_ms'] = max(0, $execution->duration_ms);
        }

        if ($execution->status === JobExecutionStatus::Failed) {
            if (filled($execution->exception_class)) {
                $payload['exception_class'] = $execution->exception_class;
            }

            if (filled($execution->exception_message)) {
                $payload['exception_message'] = $execution->exception_message;
            }

            if (filled($execution->exception_trace)) {
                $payload['exception_trace'] = mb_substr(
                    $execution->exception_trace,
                    0,
                    max(1_024, (int) config('deck.exception_trace_bytes', 65_536)),
                );
            }
        }

        if ($execution->context !== null && $execution->context !== [] && config('deck.store_context', false)) {
            $payload['context'] = $execution->context;
        }

        return $payload;
    }
}
