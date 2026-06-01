<?php

namespace Deck\Deck\Core;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeckDatabase
{
    public static function connection(): ?string
    {
        $connection = config('deck.database_connection');

        if ($connection === null || $connection === '') {
            return null;
        }

        return (string) $connection;
    }

    public static function schema(): Builder
    {
        $connection = static::connection();

        return $connection === null
            ? Schema::connection(config('database.default'))
            : Schema::connection($connection);
    }

    public static function query(): ConnectionInterface
    {
        return DB::connection(static::connection());
    }
}
