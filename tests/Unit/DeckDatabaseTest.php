<?php

use Deck\Deck\Core\DeckDatabase;
use Deck\Deck\Models\JobExecution;

it('returns null when no dedicated connection is configured', function () {
    config()->set('deck.database_connection', null);

    expect(DeckDatabase::connection())->toBeNull();
});

it('returns the configured connection name', function () {
    config()->set('deck.database_connection', 'deck');

    expect(DeckDatabase::connection())->toBe('deck');
});

it('stores models on the dedicated connection', function () {
    config()->set('database.connections.deck', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('deck.database_connection', 'deck');

    $migration = include dirname(__DIR__, 2).'/database/migrations/create_deck_tables.php.stub';
    $migration->up();

    $execution = createDeckExecution();

    expect($execution->getConnectionName())->toBe('deck')
        ->and(DeckDatabase::schema()->hasTable('deck_job_executions'))->toBeTrue()
        ->and(JobExecution::query()->where('uuid', $execution->uuid)->exists())->toBeTrue()
        ->and(JobExecution::on('testing')->where('uuid', $execution->uuid)->exists())->toBeFalse();
});
