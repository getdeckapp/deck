<?php

namespace TorMorten\Deck\Queue;

use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\CallQueuedHandler;
use TorMorten\Deck\Middleware\Blockable;

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

        $lockReleased = false;

        $middleware = array_merge(
            [new Blockable],
            method_exists($command, 'middleware') ? $command->middleware() : [],
            $command->middleware ?? [],
        );

        return (new Pipeline($this->container))->send($command)
            ->through($middleware)
            ->finally(function ($command) use (&$lockReleased) {
                if (! $lockReleased && $this->commandShouldBeUniqueUntilProcessing($command) && ! $command->job->isReleased() && $command->job->attempts() <= 1) {
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
