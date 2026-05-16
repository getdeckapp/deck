<?php

namespace TorMorten\Deck;

use Illuminate\Support\Carbon;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeferDeckSideEffects;
use TorMorten\Deck\Support\JobCancellation;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobClassBlockAudit;
use TorMorten\Deck\Support\JobExecutionRetry;
use TorMorten\Deck\Support\MarkExecutionCancelled;
use TorMorten\Deck\Support\PendingCancelResult;
use TorMorten\Deck\Support\PendingJobCancellation;
use TorMorten\Deck\Support\RetryExecutionResult;

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
