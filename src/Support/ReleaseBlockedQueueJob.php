<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Queue\Job as QueueJobContract;

class ReleaseBlockedQueueJob
{
    public static function releaseIfBlocked(QueueJobContract $job): bool
    {
        if (! JobClassBlock::isBlockedForJob($job)) {
            return false;
        }

        static::release($job);

        return true;
    }

    public static function release(QueueJobContract $job): void
    {
        $job->release(JobClassBlock::releaseDelaySeconds());

        if (! $job->isDeleted()) {
            $job->delete();
        }
    }
}
