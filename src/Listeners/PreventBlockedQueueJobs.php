<?php

namespace TorMorten\Deck\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Support\JobClassIdentifierRegistry;
use TorMorten\Deck\Support\ReleaseBlockedQueueJob;

class PreventBlockedQueueJobs
{
    public function handle(JobProcessing $event): void
    {
        JobClassIdentifierRegistry::rememberFromQueueJob($event->job);

        if (JobClassBlock::isBlockedForJob($event->job)) {
            ReleaseBlockedQueueJob::release($event->job);
        }
    }
}
