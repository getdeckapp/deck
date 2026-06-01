<?php

namespace Deck\Deck\Middleware;

use Deck\Deck\Blocking\InterceptBlockedQueueJob;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\InteractsWithQueue;

class Blockable
{
    /**
     * @param  object  $job  The queued job instance (not the queue driver job).
     * @param  callable(object): mixed  $next
     */
    public function handle(object $job, callable $next): mixed
    {
        if (in_array(InteractsWithQueue::class, class_uses_recursive($job), true)) {
            $queueJob = $job->job;

            if ($queueJob instanceof QueueJobContract && InterceptBlockedQueueJob::intercept($queueJob)) {
                return null;
            }
        }

        return $next($job);
    }
}
