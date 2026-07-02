<?php

use function Pest\Laravel\artisan;

it('reports healthy when deck can write to the database', function () {
    artisan('deck:doctor')->assertSuccessful();
});

it('warns about stale published deck migrations left in the app', function () {
    $migrationsPath = database_path('migrations');
    @mkdir($migrationsPath, 0755, true);
    $stale = $migrationsPath.'/2026_06_11_120000_create_deck_tables.php';
    file_put_contents($stale, "<?php\n");

    try {
        artisan('deck:doctor')
            ->expectsOutputToContain('Found published Deck migrations')
            ->expectsOutputToContain('2026_06_11_120000_create_deck_tables.php')
            ->assertSuccessful();
    } finally {
        @unlink($stale);
    }
});
