<?php

namespace TorMorten\Deck\Middleware;

use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\InteractsWithQueue;
use TorMorten\Deck\Support\JobCancellation;

class Cancellable
{
    /**
     * @param  object  $job  The queued job instance (not the queue driver job).
     * @param  callable(object): mixed  $next
     */
    public function handle(object $job, callable $next): mixed
    {
        if (in_array(InteractsWithQueue::class, class_uses_recursive($job), true)) {
            $queueJob = $job->job;

            if ($queueJob instanceof QueueJobContract) {
                JobCancellation::throwIfCancelled($queueJob);
            }
        }

        return $next($job);
    }
}
