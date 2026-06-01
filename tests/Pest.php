<?php

use Deck\Deck\Cloud\Agent\CloudAgentRegistry;
use Deck\Deck\Cloud\Agent\SyncThrottle;
use Deck\Deck\Core\DeckInstallation;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobClassStat;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in('Feature', 'Unit');

function enableDeckCloudForTests(): void
{
    config()->set('deck.cloud.api_key', 'test-api-key');
    config()->set('deck.cloud.url', 'https://cloud.deck.test');
    config()->set('deck.cloud.enabled', null);
    config()->set('deck.cloud.workers.interval_seconds', 30);
    config()->set('deck.cloud.workers.enabled', true);
    config()->set('deck.cloud.commands.enabled', true);
    config()->set('deck.cloud.events.enabled', true);

    CloudAgentRegistry::register(app());
}

function resetDeckCloudSyncThrottle(): void
{
    if (app()->bound(SyncThrottle::class)) {
        app(SyncThrottle::class)->reset();
    }
}

/**
 * @param  list<array<string, mixed>>  $commands
 */
function fakeDeckCloudCommandsHttp(array $commands = []): void
{
    Http::fake([
        'https://cloud.deck.test/api/v1/agent/commands/ack' => Http::response([], 200),
        'https://cloud.deck.test/api/v1/agent/commands?*' => Http::response(['commands' => $commands]),
    ]);
}

function deckProject(): string
{
    return DeckInstallation::project();
}

function deckEnvironment(): string
{
    return DeckInstallation::environment();
}

function createDeckStat(array $attributes = []): JobClassStat
{
    return JobClassStat::query()->create(array_merge([
        'project' => deckProject(),
        'environment' => deckEnvironment(),
        'job_class' => 'App\\Jobs\\ExampleJob',
        'last_status' => JobExecutionStatus::Completed,
        'last_started_at' => now()->subMinute(),
        'last_finished_at' => now(),
        'last_duration_ms' => 120,
        'success_count' => 1,
        'failure_count' => 0,
    ], $attributes));
}

function createDeckExecution(array $attributes = []): JobExecution
{
    return JobExecution::query()->create(array_merge([
        'project' => deckProject(),
        'environment' => deckEnvironment(),
        'uuid' => (string) str()->uuid(),
        'job_class' => 'App\\Jobs\\ExampleJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobExecutionStatus::Completed,
        'attempt' => 1,
        'tags' => null,
        'started_at' => now()->subSeconds(30),
        'finished_at' => now(),
        'duration_ms' => 250,
        'exception_class' => null,
        'exception_message' => null,
        'exception_trace' => null,
        'created_at' => now(),
    ], $attributes));
}
