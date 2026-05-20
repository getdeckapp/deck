<?php

namespace Deck\Deck\Support;

use Deck\Deck\Support\Concerns\RunsSilently;
use Illuminate\Contracts\Queue\Job as QueueJobContract;

class InterceptBlockedQueueJob
{
    use RunsSilently;

    public static function intercept(QueueJobContract $job): bool
    {
        return static::runSilently(
            function () use ($job): bool {
                JobClassIdentifierRegistry::rememberFromQueueJob($job);

                if (! JobClassBlock::isBlockedForJob($job)) {
                    return false;
                }

                BlockedJobExecutionRecorder::record(QueuedJobMetadata::fromQueueJob($job));
                static::removeFromQueue($job);

                return true;
            },
            false,
        );
    }

    private static function removeFromQueue(QueueJobContract $job): void
    {
        if (! $job->isDeleted()) {
            $job->delete();
        }
    }
}
