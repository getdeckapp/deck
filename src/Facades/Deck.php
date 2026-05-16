<?php

namespace TorMorten\Deck\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void cancel(string $uuid)
 * @method static bool isCancelled(string $uuid)
 * @method static bool cancelExecution(int $executionId)
 *
 * @see \TorMorten\Deck\Deck
 */
class Deck extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TorMorten\Deck\Deck::class;
    }
}
