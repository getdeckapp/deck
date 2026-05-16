<?php

namespace TorMorten\Deck\Contracts;

interface ExposesDeckContext
{
    /**
     * @return array<string, bool|int|float|string|null>
     */
    public function deckContext(): array;
}
