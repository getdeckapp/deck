<?php

namespace Deck\Deck\Core;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeckDatabase
{
    /**
     * The dedicated connection name Deck provides out of the box.
     */
    public const DEFAULT_CONNECTION = 'deck';

    /**
     * The connection name Deck stores its tables on.
     *
     * Defaults to the dedicated "deck" connection, which itself falls back to
     * the application's default connection when not separately configured.
     */
    public static function connection(): string
    {
        $connection = config('deck.database_connection');

        if ($connection === null || $connection === '') {
            return self::DEFAULT_CONNECTION;
        }

        return (string) $connection;
    }

    /**
     * Ensure Deck's connection exists.
     *
     * When Deck's connection is not explicitly defined, it is provisioned from
     * a clone of the application's default connection with any DECK_DB_* values
     * (exposed via config('deck.database')) overlaid on top. Fields without an
     * explicit override retain the default connection's value.
     */
    public static function register(): void
    {
        $connection = self::connection();

        if (config("database.connections.{$connection}") !== null) {
            return;
        }

        $default = (string) config('database.default');
        $base = config("database.connections.{$default}");

        $base = is_array($base) ? $base : [];

        config(["database.connections.{$connection}" => array_merge($base, self::overrides())]);
    }

    /**
     * The DECK_DB_* overrides that are explicitly set (non-null).
     *
     * @return array<string, mixed>
     */
    protected static function overrides(): array
    {
        $overrides = config('deck.database');

        if (! is_array($overrides)) {
            return [];
        }

        return array_filter($overrides, static fn ($value): bool => $value !== null);
    }

    public static function schema(): Builder
    {
        return Schema::connection(self::connection());
    }

    public static function query(): ConnectionInterface
    {
        return DB::connection(self::connection());
    }
}
