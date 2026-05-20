<?php

namespace Deck\Deck\Support;

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
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function runSilentlyVoid(callable $callback): void
    {
        static::runSilently($callback);
    }
}
