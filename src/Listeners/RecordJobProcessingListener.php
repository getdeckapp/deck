<?php

namespace Deck\Deck\Listeners;

use Deck\Deck\Support\InterceptBlockedQueueJob;
use Illuminate\Queue\Events\JobProcessing;

class RecordJobProcessingListener
{
    public function __construct(
        private readonly RecordJobExecution $recorder,
    ) {}

    public function handle(JobProcessing $event): void
    {
        if (InterceptBlockedQueueJob::intercept($event->job)) {
            return;
        }

        $this->recorder->handleProcessing($event);
    }
}
