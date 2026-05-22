<?php

namespace Deck\Deck\Queue;

use Deck\Deck\Middleware\Blockable;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\CallQueuedHandler;

class DeckCallQueuedHandler extends CallQueuedHandler
{
    /**
     * @param  mixed  $command
     * @return mixed
     */
    protected function dispatchThroughMiddleware(Job $job, $command)
    {
        if ($command instanceof \__PHP_Incomplete_Class) {
            throw new Exception('Job is incomplete class: '.json_encode($command));
        }

        $middleware = array_merge(
            [new Blockable],
            method_exists($command, 'middleware') ? $command->middleware() : [],
            $command->middleware ?? [],
        );

        $lockReleased = false;

        return (new Pipeline($this->container))->send($command)
            ->through($middleware)
            ->finally(function ($command) use (&$lockReleased, $job) {
                $queueJob = (is_object($command) && isset($command->job)) ? $command->job : $job;

                if (! $lockReleased
                    && $this->commandShouldBeUniqueUntilProcessing($command)
                    && $queueJob instanceof Job
                    && ! $queueJob->isReleased()
                    && $queueJob->attempts() <= 1) {
                    $this->ensureUniqueJobLockIsReleased($command);
                }
            })
            ->then(function ($command) use ($job, &$lockReleased) {
                if ($this->commandShouldBeUniqueUntilProcessing($command) && $job->attempts() <= 1) {
                    $this->ensureUniqueJobLockIsReleased($command);

                    $lockReleased = true;
                }

                return $this->dispatcher->dispatchNow(
                    $command, $this->resolveHandler($job, $command)
                );
            });
    }
}
