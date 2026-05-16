<?php

namespace TorMorten\Deck\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TorMorten\Deck\Deck
 */
class Deck extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TorMorten\Deck\Deck::class;
    }
}
