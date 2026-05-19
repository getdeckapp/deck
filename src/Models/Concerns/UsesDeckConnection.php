<?php

namespace Deck\Deck\Models\Concerns;

use Deck\Deck\Support\DeckDatabase;

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
