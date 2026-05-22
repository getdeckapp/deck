<?php

namespace Deck\Deck\Support;

/**
 * Runs lightweight Deck side effects inline. Queue workers and HTTP must not rely
 * on Laravel's defer() here — Horizon never flushes deferred callbacks per job, and
 * Swoole's global defer() breaks outside coroutines.
 */
class DeferJobLifecycleRecording
{
    public static function run(callable $callback): void
    {
        $callback();
    }
}
