<?php

namespace TorMorten\Deck\Tests;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use TorMorten\Deck\DeckServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DeckServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = include __DIR__.'/../database/migrations/create_deck_tables.php.stub';

        $migration->up();
    }

    protected function defineEnvironment($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'array');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('deck.auth', fn () => true);
        config()->set('deck.retention_days', 90);
        config()->set('deck.project', 'test');
        config()->set('deck.environment', 'testing');
    }
}
