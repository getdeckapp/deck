<?php

namespace Deck\Deck\Support;

/**
 * Defers terminal execution writes until Laravel terminates the current cycle
 * (HTTP response sent, or queue worker finished the job event) so dispatch and
 * worker hot paths do less synchronous database work.
 */
class DeferJobLifecycleRecording
{
    public static function run(callable $callback): void
    {
        if (app()->runningUnitTests()) {
            $callback();

            return;
        }

        defer($callback);
    }
}
