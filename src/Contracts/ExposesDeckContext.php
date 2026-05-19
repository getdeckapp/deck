<?php

namespace Deck\Deck\Contracts;

interface ExposesDeckContext
{
    /**
     * @return array<string, bool|int|float|string|null>
     */
    public function deckContext(): array;
}
