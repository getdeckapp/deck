<?php

namespace Deck\Deck\Queue;

use Deck\Deck\Middleware\Blockable;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
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

        return (new Pipeline($this->container))->send($command)
            ->through($middleware)
            ->then(function ($command) use ($job) {
                if ($command instanceof ShouldBeUniqueUntilProcessing) {
                    $this->ensureUniqueJobLockIsReleased($command);
                }

                return $this->dispatcher->dispatchNow(
                    $command, $this->resolveHandler($job, $command)
                );
            });
    }
}
