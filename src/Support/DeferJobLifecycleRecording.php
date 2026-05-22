<?php

namespace Deck\Deck\Support;

use function Illuminate\Support\defer;

/**
 * Defers terminal execution writes on HTTP requests. Queue workers (Horizon,
 * queue:work) run synchronously because defer callbacks are only flushed on
 * {@see \Illuminate\Queue\Events\JobAttempted} for successful jobs.
 *
 * Uses {@see defer()} from Illuminate\Support so Swoole's global defer() is not
 * invoked when the extension is loaded (e.g. Octane).
 */
class DeferJobLifecycleRecording
{
    public static function run(callable $callback): void
    {
        if (static::runsSynchronously()) {
            $callback();

            return;
        }

        defer($callback, always: true);
    }

    public static function runsSynchronously(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        // Horizon and queue:work never flush defer() via HTTP teardown; JobAttempted
        // may not run on older Laravel, and failed jobs skip non-always defer callbacks.
        return app()->runningInConsole();
    }
}
