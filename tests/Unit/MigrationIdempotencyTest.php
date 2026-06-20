<?php

use Deck\Deck\Core\DeckDatabase;

// The base TestCase already builds the full Deck schema before each test, so
// re-running every migration here reproduces an existing install that adopts
// the package-shipped migrations under a different recorded timestamp. Each
// up() must be a safe no-op rather than failing on already-present tables.
it('runs every migration safely against an existing schema', function () {
    $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $migration = include $file;

        $migration->up();
    }

    $schema = DeckDatabase::schema();

    expect($schema->hasTable(config('deck.tables.job_executions')))->toBeTrue()
        ->and($schema->hasTable(config('deck.tables.job_class_stats')))->toBeTrue()
        ->and($schema->hasColumn(config('deck.tables.job_executions'), 'dispatched_at'))->toBeTrue();
});
