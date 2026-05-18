<?php

use TorMorten\Deck\Commands\CheckAlertsCommand;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Support\DeckAlertChecker;

it('detects job classes above the configured failure-rate threshold', function () {
    config()->set('deck.alerts.enabled', true);
    config()->set('deck.alerts.failure_rate_jobs', [
        'App\\Jobs\\FlakyJob' => [
            'max_failure_rate' => 20,
            'window_hours' => 24,
            'min_samples' => 3,
        ],
    ]);

    foreach (range(1, 2) as $ignored) {
        createDeckExecution([
            'job_class' => 'App\\Jobs\\FlakyJob',
            'status' => JobExecutionStatus::Completed,
            'started_at' => now()->subHour(),
        ]);
    }

    foreach (range(1, 2) as $ignored) {
        createDeckExecution([
            'job_class' => 'App\\Jobs\\FlakyJob',
            'status' => JobExecutionStatus::Failed,
            'started_at' => now()->subHour(),
        ]);
    }

    $alerts = app(DeckAlertChecker::class)->failureRates();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->failureRate)->toBe(50.0);
});

it('reports failure-rate alerts from the check-alerts command', function () {
    config()->set('deck.alerts.enabled', true);
    config()->set('deck.alerts.stale_jobs', []);
    config()->set('deck.alerts.failure_rate_jobs', [
        'App\\Jobs\\FlakyJob' => [
            'max_failure_rate' => 10,
            'window_hours' => 24,
            'min_samples' => 2,
        ],
    ]);

    createDeckExecution([
        'job_class' => 'App\\Jobs\\FlakyJob',
        'status' => JobExecutionStatus::Failed,
        'started_at' => now()->subHour(),
    ]);

    createDeckExecution([
        'job_class' => 'App\\Jobs\\FlakyJob',
        'status' => JobExecutionStatus::Failed,
        'started_at' => now()->subHour(),
    ]);

    $this->artisan(CheckAlertsCommand::class)
        ->expectsOutputToContain('exceeded the configured failure-rate threshold')
        ->expectsOutputToContain('App\\Jobs\\FlakyJob')
        ->assertSuccessful();
});
