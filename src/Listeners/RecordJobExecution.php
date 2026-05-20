<?php

namespace Deck\Deck\Listeners;

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Exceptions\JobCancelledException;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckInstallation;
use Deck\Deck\Support\DeckResilience;
use Deck\Deck\Support\DeferJobLifecycleRecording;
use Deck\Deck\Support\JobCancellation;
use Deck\Deck\Support\JobClassBlock;
use Deck\Deck\Support\JobClassIdentifierRegistry;
use Deck\Deck\Support\JobExecutionTiming;
use Deck\Deck\Support\JobProgress;
use Deck\Deck\Support\QueuedJobMetadata;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;

class RecordJobExecution
{
    public function __construct(
        private JobExecutionRecorder $recorder,
    ) {}

    public function handleProcessing(JobProcessing $event): void
    {
        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);

            if (JobClassBlock::isBlockedForJob($event->job)) {
                return;
            }

            JobClassIdentifierRegistry::rememberFromQueueJob($event->job);

            $startedAt = Carbon::now();
            JobExecutionTiming::remember($metadata->uuid, $metadata->attempt, $startedAt);

            $this->recorder->record(new JobExecutionRecord(
                metadata: $metadata,
                project: DeckInstallation::project(),
                environment: DeckInstallation::environment(),
                status: JobExecutionStatus::Running,
                startedAt: $startedAt,
                tags: $metadata->tags,
            ));
        });
    }

    public function handleProcessed(JobProcessed $event): void
    {
        DeferJobLifecycleRecording::run(fn () => $this->recordProcessed($event));
    }

    public function handleFailed(JobFailed $event): void
    {
        DeferJobLifecycleRecording::run(fn () => $this->recordFailed($event));
    }

    private function recordProcessed(JobProcessed $event): void
    {
        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);
            $finishedAt = Carbon::now();

            $status = JobExecution::query()
                ->where('uuid', $metadata->uuid)
                ->where('attempt', $metadata->attempt)
                ->value('status');

            if ($status === JobExecutionStatus::Blocked) {
                JobExecutionTiming::forget($metadata->uuid, $metadata->attempt);

                return;
            }

            if ($status === null && $event->job->isDeletedOrReleased()) {
                JobExecutionTiming::forget($metadata->uuid, $metadata->attempt);

                return;
            }

            $startedAt = JobExecutionTiming::resolve($metadata->uuid, $metadata->attempt)
                ?? $this->startedAtFromDatabase($metadata->uuid, $metadata->attempt)
                ?? $finishedAt;

            $wasCancelled = JobCancellation::consumeIfCancelled($metadata->uuid);

            $this->recorder->record(new JobExecutionRecord(
                metadata: $metadata,
                project: DeckInstallation::project(),
                environment: DeckInstallation::environment(),
                status: $wasCancelled ? JobExecutionStatus::Cancelled : JobExecutionStatus::Completed,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                durationMs: (int) $startedAt->diffInMilliseconds($finishedAt),
                tags: $metadata->tags,
            ));

            JobProgress::clear($metadata->uuid);
        });
    }

    private function recordFailed(JobFailed $event): void
    {
        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);
            $finishedAt = Carbon::now();
            $exception = $event->exception;

            $startedAt = JobExecutionTiming::resolve($metadata->uuid, $metadata->attempt)
                ?? $this->startedAtFromDatabase($metadata->uuid, $metadata->attempt)
                ?? $finishedAt;

            $isCancelled = $exception instanceof JobCancelledException;

            if ($isCancelled) {
                JobCancellation::consumeIfCancelled($metadata->uuid);
            }

            $this->recorder->record(new JobExecutionRecord(
                metadata: $metadata,
                project: DeckInstallation::project(),
                environment: DeckInstallation::environment(),
                status: $isCancelled ? JobExecutionStatus::Cancelled : JobExecutionStatus::Failed,
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                durationMs: (int) $startedAt->diffInMilliseconds($finishedAt),
                tags: $metadata->tags,
                exceptionClass: $isCancelled ? null : $exception::class,
                exceptionMessage: $isCancelled ? null : $this->truncateExceptionMessage($exception->getMessage()),
                exceptionTrace: $isCancelled ? null : $this->truncateExceptionTrace($exception),
            ));

            JobProgress::clear($metadata->uuid);
        });
    }

    private function startedAtFromDatabase(string $uuid, int $attempt): ?Carbon
    {
        $startedAt = JobExecution::query()
            ->where('uuid', $uuid)
            ->where('attempt', $attempt)
            ->value('started_at');

        return $startedAt instanceof Carbon ? $startedAt : null;
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
