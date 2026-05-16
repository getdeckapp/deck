<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Queue\ShouldQueue;

class InterceptBlockedDispatch
{
    public static function intercept(object $command): bool
    {
        if (! $command instanceof ShouldQueue) {
            return false;
        }

        if (! JobClassBlock::isBlockedForCommand($command)) {
            return false;
        }

        BlockedJobExecutionRecorder::record(QueuedJobMetadata::fromCommand($command));

        return true;
    }
}
