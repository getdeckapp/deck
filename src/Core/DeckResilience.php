<?php

namespace Deck\Deck\Core;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deck observability must never take down application jobs when Redis or the
 * database is unavailable — failures are swallowed here by design.
 */
class DeckResilience
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @param  TReturn  $default
     * @return TReturn
     */
    public static function runSilently(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            static::logFailure($exception);

            return $default;
        }
    }

    public static function runSilentlyVoid(callable $callback): void
    {
        static::runSilently($callback);
    }

    private static function logFailure(Throwable $exception): void
    {
        if (! config('deck.log_recording_failures', true)) {
            return;
        }

        Log::warning('Deck recording failed.', [
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
