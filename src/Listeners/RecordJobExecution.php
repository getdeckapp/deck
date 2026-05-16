<?php

namespace TorMorten\Deck\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use TorMorten\Deck\Contracts\JobExecutionRecorder;
use TorMorten\Deck\Data\JobExecutionRecord;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Exceptions\JobCancelledException;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckInstallation;
use TorMorten\Deck\Support\JobCancellation;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobClassIdentifierRegistry;
use TorMorten\Deck\Support\QueuedJobMetadata;

class RecordJobExecution
{
    public function __construct(
        private JobExecutionRecorder $recorder,
    ) {}

    public function handleProcessing(JobProcessing $event): void
    {
        $metadata = QueuedJobMetadata::fromQueueJob($event->job);

        if (JobClassBlock::isBlockedForJob($event->job)) {
            return;
        }

        JobClassIdentifierRegistry::rememberFromQueueJob($event->job);

        $this->recorder->record(new JobExecutionRecord(
            metadata: $metadata,
            project: DeckInstallation::project(),
            environment: DeckInstallation::environment(),
            status: JobExecutionStatus::Running,
            startedAt: Carbon::now(),
            tags: $metadata->tags,
        ));
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $metadata = QueuedJobMetadata::fromQueueJob($event->job);
        $finishedAt = Carbon::now();

        $execution = JobExecution::query()
            ->where('uuid', $metadata->uuid)
            ->where('attempt', $metadata->attempt)
            ->first();

        if ($execution?->status === JobExecutionStatus::Blocked) {
            return;
        }

        if ($execution === null && $event->job->isDeletedOrReleased()) {
            return;
        }

        $startedAt = $execution?->started_at ?? $finishedAt;
        $durationMs = (int) $startedAt->diffInMilliseconds($finishedAt);

        $wasCancelled = JobCancellation::isCancelled($metadata->uuid);

        $this->recorder->record(new JobExecutionRecord(
            metadata: $metadata,
            project: DeckInstallation::project(),
            environment: DeckInstallation::environment(),
            status: $wasCancelled ? JobExecutionStatus::Cancelled : JobExecutionStatus::Completed,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            tags: $metadata->tags,
        ));

        if ($wasCancelled) {
            JobCancellation::clear($metadata->uuid);
        }
    }

    public function handleFailed(JobFailed $event): void
    {
        $metadata = QueuedJobMetadata::fromQueueJob($event->job);
        $finishedAt = Carbon::now();
        $exception = $event->exception;

        $execution = JobExecution::query()
            ->where('uuid', $metadata->uuid)
            ->where('attempt', $metadata->attempt)
            ->first();

        $startedAt = $execution?->started_at ?? $finishedAt;
        $durationMs = (int) $startedAt->diffInMilliseconds($finishedAt);

        $isCancelled = $exception instanceof JobCancelledException;

        $this->recorder->record(new JobExecutionRecord(
            metadata: $metadata,
            project: DeckInstallation::project(),
            environment: DeckInstallation::environment(),
            status: $isCancelled ? JobExecutionStatus::Cancelled : JobExecutionStatus::Failed,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            tags: $metadata->tags,
            exceptionClass: $isCancelled ? null : $exception::class,
            exceptionMessage: $isCancelled ? null : $this->truncateExceptionMessage($exception->getMessage()),
            exceptionTrace: $isCancelled ? null : $this->truncateExceptionTrace($exception),
        ));

        if ($isCancelled) {
            JobCancellation::clear($metadata->uuid);
        }
    }

    private function truncateExceptionMessage(string $message): string
    {
        return mb_substr($message, 0, 2000);
    }

    private function truncateExceptionTrace(\Throwable $exception): string
    {
        $limit = max(1_024, (int) config('deck.exception_trace_bytes', 65_536));

        return mb_substr($exception->getTraceAsString(), 0, $limit);
    }
}
