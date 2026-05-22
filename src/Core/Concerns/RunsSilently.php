<?php

namespace Deck\Deck\Core\Concerns;

/**
 * Swallows failures so Deck observability never breaks application jobs.
 * Must live on the calling class (not DeckResilience) so PHPStan allows
 * private static helpers inside the passed closures.
 */
trait RunsSilently
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @param  TReturn  $default
     * @return TReturn
     */
    private static function runSilently(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return $default;
        }
    }

    private static function runSilentlyVoid(callable $callback): void
    {
        static::runSilently($callback);
    }
}
