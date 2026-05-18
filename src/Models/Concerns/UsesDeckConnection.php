<?php

namespace TorMorten\Deck\Models\Concerns;

use TorMorten\Deck\Support\DeckDatabase;

trait UsesDeckConnection
{
    public function getConnectionName(): ?string
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        return DeckDatabase::connection();
    }
}
