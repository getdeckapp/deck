<?php

namespace Deck\Deck\Listeners;

use Deck\Deck\Blocking\InterceptBlockedQueueJob;
use Deck\Deck\Blocking\JobClassBlock;
use Deck\Deck\Blocking\JobClassIdentifierRegistry;
use Deck\Deck\Cancellation\JobCancellation;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Core\DeckInstallation;
use Deck\Deck\Core\DeckResilience;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Exceptions\JobCancelledException;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Recording\JobExecutionTiming;
use Deck\Deck\Recording\JobProgress;
use Deck\Deck\Recording\QueuedJobMetadata;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;

class RecordJobExecution
{
    public function __construct(
        private JobExecutionRecorder $recorder,
    ) {}

    public function handleJobProcessing(JobProcessing $event): void
    {
        if (InterceptBlockedQueueJob::intercept($event->job)) {
            return;
        }

        $this->handleProcessing($event);
    }

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
                waitMs: $this->resolveWaitMs($metadata, $startedAt),
                tags: $metadata->tags,
            ));
        });
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $this->recordProcessed($event);
    }

    public function handleFailed(JobFailed $event): void
    {
        $this->recordFailed($event);
    }

    /**
     * Ensures terminal status is persisted when earlier listeners did not run
     * (defer bugs, swallowed errors, or jobs that skipped the running row).
     */
    public function handleJobAttempted(JobAttempted $event): void
    {
        if (in_array($event->connectionName, ['sync', 'deferred'], true)) {
            return;
        }

        DeckResilience::runSilentlyVoid(function () use ($event): void {
            $metadata = QueuedJobMetadata::fromQueueJob($event->job);

            $status = JobExecution::query()
                ->where('uuid', $metadata->uuid)
                ->where('attempt', $metadata->attempt)
                ->value('status');

            if ($status !== null && $status !== JobExecutionStatus::Running) {
                return;
            }

            if ($status === null) {
                $this->handleProcessing(new JobProcessing($event->connectionName, $event->job));
            }

            if ($event->successful()) {
                $this->recordProcessed(new JobProcessed($event->connectionName, $event->job));

                return;
            }

            if ($event->exception !== null) {
                $this->recordFailed(new JobFailed($event->connectionName, $event->job, $event->exception));
            }
        });
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
                waitMs: $this->waitMsFromDatabase($metadata->uuid, $metadata->attempt),
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
                waitMs: $this->waitMsFromDatabase($metadata->uuid, $metadata->attempt),
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

    private function resolveWaitMs(QueuedJobMetadata $metadata, Carbon $startedAt): ?int
    {
        $dispatchedAt = $metadata->observability?->dispatchedAt;

        if ($dispatchedAt === null) {
            return null;
        }

        return max(0, (int) $dispatchedAt->diffInMilliseconds($startedAt));
    }

    private function waitMsFromDatabase(string $uuid, int $attempt): ?int
    {
        $waitMs = JobExecution::query()
            ->where('uuid', $uuid)
            ->where('attempt', $attempt)
            ->value('wait_ms');

        return is_numeric($waitMs) ? (int) $waitMs : null;
    }
}
