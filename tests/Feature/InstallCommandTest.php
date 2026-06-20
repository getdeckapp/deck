<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('publishes deck install artifacts', function () {
    $configPath = config_path('deck.php');
    $migrationDir = database_path('migrations');
    $assetPath = public_path('vendor/deck/deck.css');

    if (File::exists($configPath)) {
        File::delete($configPath);
    }

    if (File::exists($assetPath)) {
        File::delete($assetPath);
    }

    Artisan::call('deck:install');

    expect(Artisan::output())->toContain('Publishing Deck configuration')
        ->and(File::exists($configPath))->toBeTrue()
        ->and(File::exists($assetPath))->toBeTrue();

    // Deck migrations run from the package and are not published into the app,
    // so the app's migrations directory should not contain deck migrations.
    $migrationFiles = File::glob($migrationDir.'/*deck*.php');

    expect($migrationFiles)->toBeEmpty();
});
