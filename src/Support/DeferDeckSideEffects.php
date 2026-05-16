<?php

namespace TorMorten\Deck\Support;

class DeferDeckSideEffects
{
    public static function run(callable $callback): void
    {
        if (static::shouldDefer()) {
            defer($callback);

            return;
        }

        $callback();
    }

    public static function shouldDefer(): bool
    {
        if (! config('deck.defer_side_effects', true)) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return ! app()->runningInConsole();
    }
}
