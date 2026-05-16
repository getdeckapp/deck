<?php

namespace TorMorten\Deck;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TorMorten\Deck\Commands\DeckCommand;

class DeckServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('deck')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_deck_table')
            ->hasCommand(DeckCommand::class);
    }
}
