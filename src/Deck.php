<?php

namespace Deck\Deck;

use Deck\Deck\Data\ClearQueueResult;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeferDeckSideEffects;
use Deck\Deck\Support\JobCancellation;
use Deck\Deck\Support\JobClassBlock;
use Deck\Deck\Support\JobClassBlockAudit;
use Deck\Deck\Support\JobExecutionRetry;
use Deck\Deck\Support\JobProgress;
use Deck\Deck\Support\MarkExecutionCancelled;
use Deck\Deck\Support\PendingCancelResult;
use Deck\Deck\Support\PendingJobCancellation;
use Deck\Deck\Support\QueueAdmin;
use Deck\Deck\Support\RetryExecutionResult;
use Illuminate\Support\Carbon;

class Deck
{
    public function cancel(string $uuid): void
    {
        JobCancellation::cancel($uuid);
    }

    public function isCancelled(string $uuid): bool
    {
        return JobCancellation::isCancelled($uuid);
    }

    public function retryExecution(string $uuid, ?int $attempt = null): RetryExecutionResult
    {
        $query = JobExecution::query()
            ->forInstallation()
            ->where('uuid', $uuid)
            ->where('status', JobExecutionStatus::Failed);

        if ($attempt !== null) {
            $query->where('attempt', $attempt);
        } else {
            $query->orderByDesc('attempt');
        }

        $execution = $query->first();

        if ($execution === null) {
            return new RetryExecutionResult(
                success: false,
                message: 'Failed execution not found.',
            );
        }

        return app(JobExecutionRetry::class)->retry($execution);
    }

    public function cancelPending(string $uuid, ?string $connection = null, ?string $queue = null, bool $force = false): PendingCancelResult
    {
        return PendingJobCancellation::cancel($uuid, $connection, $queue, $force);
    }

    public function cancelExecution(string $uuid, ?int $attempt = null): bool
    {
        return $this->findRunningExecution($uuid, $attempt) !== null
            && $this->requestCancelExecution($uuid, $attempt) !== null;
    }

    public function requestCancelExecution(string $uuid, ?int $attempt = null): ?PendingCancelResult
    {
        $execution = $this->findRunningExecution($uuid, $attempt);

        if ($execution === null) {
            return null;
        }

        return PendingJobCancellation::cancel($execution->uuid, $execution->connection, $execution->queue);
    }

    public function forceCancelExecution(string $uuid, ?int $attempt = null): ?PendingCancelResult
    {
        $execution = $this->findRunningExecution($uuid, $attempt);

        if ($execution === null) {
            return null;
        }

        $result = PendingJobCancellation::cancel(
            $execution->uuid,
            $execution->connection,
            $execution->queue,
            force: true,
        );

        MarkExecutionCancelled::mark($execution);

        return $result;
    }

    public function cancelAllRunningForClass(string $jobClass, bool $force = false): int
    {
        $executions = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $jobClass)
            ->where('status', JobExecutionStatus::Running)
            ->get();

        foreach ($executions as $execution) {
            PendingJobCancellation::cancel($execution->uuid, $execution->connection, $execution->queue, $force);

            if ($force) {
                MarkExecutionCancelled::mark($execution);
            }
        }

        return $executions->count();
    }

    public function blockClass(string $jobClass, ?Carbon $until = null, bool $cancelRunning = true, ?string $reason = null): void
    {
        JobClassBlock::block($jobClass, $until, $reason);

        if ($cancelRunning) {
            DeferDeckSideEffects::run(fn () => $this->cancelAllRunningForClass($jobClass));
        }
    }

    public function unblockClass(string $jobClass): void
    {
        JobClassBlock::unblock($jobClass);
    }

    public function isClassBlocked(string $jobClass): bool
    {
        return JobClassBlock::isBlocked($jobClass);
    }

    public function classBlockedUntil(string $jobClass): ?Carbon
    {
        return JobClassBlock::blockedUntil($jobClass);
    }

    public function classBlockAudit(string $jobClass): ?JobClassBlockAudit
    {
        return JobClassBlock::audit($jobClass);
    }

    public function updateProgress(string $uuid, int $percent, ?string $message = null): void
    {
        JobProgress::update($uuid, $percent, $message);
    }

    public function clearQueue(string $connection, string $queue): ClearQueueResult
    {
        return QueueAdmin::clear($connection, $queue);
    }

    private function findRunningExecution(string $uuid, ?int $attempt): ?JobExecution
    {
        $query = JobExecution::query()
            ->forInstallation()
            ->where('uuid', $uuid)
            ->where('status', JobExecutionStatus::Running);

        if ($attempt !== null) {
            $query->where('attempt', $attempt);
        } else {
            $query->orderByDesc('attempt');
        }

        return $query->first();
    }
}
