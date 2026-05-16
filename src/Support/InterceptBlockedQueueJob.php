<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Queue\Job as QueueJobContract;

class InterceptBlockedQueueJob
{
    public static function intercept(QueueJobContract $job): bool
    {
        JobClassIdentifierRegistry::rememberFromQueueJob($job);

        if (! JobClassBlock::isBlockedForJob($job)) {
            return false;
        }

        BlockedJobExecutionRecorder::record(QueuedJobMetadata::fromQueueJob($job));
        static::removeFromQueue($job);

        return true;
    }

    private static function removeFromQueue(QueueJobContract $job): void
    {
        if (! $job->isDeleted()) {
            $job->delete();
        }
    }
}
