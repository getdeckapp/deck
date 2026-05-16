<?php

namespace TorMorten\Deck;

use Illuminate\Support\Carbon;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\JobCancellation;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobExecutionRetry;
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

    public function cancelExecution(string $uuid, ?int $attempt = null): bool
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

        $execution = $query->first();

        if ($execution === null) {
            return false;
        }

        JobCancellation::cancel($execution->uuid);

        return true;
    }

    public function cancelAllRunningForClass(string $jobClass): int
    {
        $executions = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $jobClass)
            ->where('status', JobExecutionStatus::Running)
            ->get();

        foreach ($executions as $execution) {
            JobCancellation::cancel($execution->uuid);
        }

        return $executions->count();
    }

    public function blockClass(string $jobClass, ?Carbon $until = null, bool $cancelRunning = true): void
    {
        JobClassBlock::block($jobClass, $until);

        if ($cancelRunning) {
            $this->cancelAllRunningForClass($jobClass);
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
}
