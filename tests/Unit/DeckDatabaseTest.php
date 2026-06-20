<?php

use Deck\Deck\Core\DeckDatabase;
use Deck\Deck\Models\JobExecution;

it('defaults to the dedicated deck connection', function () {
    config()->set('deck.database_connection', null);

    expect(DeckDatabase::connection())->toBe(DeckDatabase::DEFAULT_CONNECTION);
});

it('returns the configured connection name', function () {
    config()->set('deck.database_connection', 'reporting');

    expect(DeckDatabase::connection())->toBe('reporting');
});

it('provides the deck connection by cloning the default connection', function () {
    config()->set('deck.database_connection', null);
    config()->set('database.connections.deck', null);
    config()->set('deck.database', null);

    DeckDatabase::register();

    expect(config('database.connections.deck'))
        ->toBe(config('database.connections.'.config('database.default')));
});

it('overlays explicit DECK_DB_* overrides on the cloned default connection', function () {
    config()->set('deck.database_connection', null);
    config()->set('database.connections.deck', null);
    config()->set('deck.database', [
        'database' => 'deck_analytics',
        'username' => 'deck_user',
        'host' => null,
    ]);

    DeckDatabase::register();

    $default = config('database.connections.'.config('database.default'));

    expect(config('database.connections.deck.database'))->toBe('deck_analytics')
        ->and(config('database.connections.deck.username'))->toBe('deck_user');
});

it('falls back to the default connection for unset override fields', function () {
    config()->set('deck.database_connection', null);
    config()->set('database.connections.deck', null);
    config()->set('deck.database', [
        'database' => 'deck_analytics',
        'driver' => null,
        'host' => null,
    ]);

    DeckDatabase::register();

    $default = config('database.connections.'.config('database.default'));

    expect(config('database.connections.deck.driver'))->toBe($default['driver'] ?? null)
        ->and(config('database.connections.deck.host'))->toBe($default['host'] ?? null);
});

it('does not override an explicitly defined deck connection', function () {
    config()->set('deck.database_connection', null);
    config()->set('database.connections.deck', [
        'driver' => 'sqlite',
        'database' => '/tmp/deck.sqlite',
        'prefix' => '',
    ]);

    DeckDatabase::register();

    expect(config('database.connections.deck.database'))->toBe('/tmp/deck.sqlite');
});

it('stores models on a dedicated connection', function () {
    config()->set('database.connections.reporting', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('deck.database_connection', 'reporting');

    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_05_22_220607_create_deck_tables.php';
    $migration->up();

    $execution = createDeckExecution();

    expect($execution->getConnectionName())->toBe('reporting')
        ->and(DeckDatabase::schema()->hasTable('deck_job_executions'))->toBeTrue()
        ->and(JobExecution::query()->where('uuid', $execution->uuid)->exists())->toBeTrue()
        ->and(JobExecution::on(DeckDatabase::DEFAULT_CONNECTION)->where('uuid', $execution->uuid)->exists())->toBeFalse();
});
