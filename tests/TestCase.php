<?php

namespace Deck\Deck\Tests;

use Deck\Deck\DeckServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

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
        $migration = include __DIR__.'/../database/migrations/2026_05_22_220607_create_deck_tables.php';

        $migration->up();

        $observabilityMigration = include __DIR__.'/../database/migrations/2026_05_27_143722_add_observability_v2_to_deck_job_executions.php';

        $observabilityMigration->up();
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
        config()->set('queue.connections.redis', [
            'driver' => 'sync',
        ]);
        config()->set('cache.default', 'array');
        config()->set('deck.cancel_cache_store', 'array');
        config()->set('deck.block_cache_store', 'array');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('deck.auth', fn () => true);
        config()->set('deck.retention_days', 90);
        config()->set('deck.project', 'test');
        config()->set('deck.environment', 'testing');
        config()->set('deck.unprocessed_queues.enabled', false);
        config()->set('deck.cloud.api_key', 'test-api-key');
        config()->set('deck.cloud.url', 'https://cloud.deck.test');
    }
}
