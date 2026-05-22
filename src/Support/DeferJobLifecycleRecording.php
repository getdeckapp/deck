<?php

namespace Deck\Deck\Support;

use function Illuminate\Support\defer;

/**
 * Defers terminal execution writes until Laravel terminates the current cycle
 * (HTTP response sent, or queue worker finished the job event) so dispatch and
 * worker hot paths do less synchronous database work.
 *
 * Uses {@see defer()} from Illuminate\Support so Swoole's global defer() is not
 * invoked when the extension is loaded (e.g. Octane).
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
