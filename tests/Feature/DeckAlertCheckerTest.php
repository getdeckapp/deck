<?php

use TorMorten\Deck\Support\DeckAlertChecker;

it('detects stale job classes from config rules', function () {
    config()->set('deck.alerts.enabled', true);
    config()->set('deck.alerts.stale_jobs', [
        'App\\Jobs\\StaleJob' => ['max_age_hours' => 24],
    ]);

    createDeckStat([
        'job_class' => 'App\\Jobs\\StaleJob',
        'last_finished_at' => now()->subDays(2),
    ]);

    $alerts = app(DeckAlertChecker::class)->staleJobs();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->jobClass)->toBe('App\\Jobs\\StaleJob');
});

it('returns no alerts when jobs ran recently', function () {
    config()->set('deck.alerts.enabled', true);
    config()->set('deck.alerts.stale_jobs', [
        'App\\Jobs\\FreshJob' => ['max_age_hours' => 24],
    ]);

    createDeckStat([
        'job_class' => 'App\\Jobs\\FreshJob',
        'last_finished_at' => now()->subHour(),
    ]);

    expect(app(DeckAlertChecker::class)->staleJobs())->toBeEmpty();
});
