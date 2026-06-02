<?php

use Deck\Deck\Commands\RunScheduledCommand;
use Deck\Deck\Models\JobExecution;

beforeEach(function () {
    config()->set('deck.cloud.api_key', null);
    config()->set('deck.cloud.url', null);
    config()->set('deck.alerts.enabled', false);
});

it('runs prune and skips commands whose features are disabled', function () {
    createDeckExecution(['created_at' => now()->subDays(120)]);

    $this->artisan(RunScheduledCommand::class)
        ->expectsOutputToContain('deck:prune')
        ->expectsOutputToContain('deck:report-workers')
        ->expectsOutputToContain('SKIPPED')
        ->assertSuccessful();

    expect(JobExecution::query()->count())->toBe(0);
});

it('runs check-alerts when alerts are enabled', function () {
    config()->set('deck.alerts.enabled', true);

    $this->artisan(RunScheduledCommand::class)
        ->expectsOutputToContain('deck:check-alerts')
        ->assertSuccessful();
});

it('runs cloud commands when Deck Cloud is enabled', function () {
    config()->set('deck.cloud.api_key', 'test-api-key');
    config()->set('deck.cloud.url', 'https://cloud.deck.test');
    config()->set('deck.cloud.workers.enabled', false);
    config()->set('deck.cloud.commands.enabled', true);

    fakeDeckCloudCommandsHttp();

    $this->artisan(RunScheduledCommand::class)
        ->expectsOutputToContain('deck:poll-commands')
        ->assertSuccessful();
});
