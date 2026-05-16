<?php

use TorMorten\Deck\Commands\CheckAlertsCommand;

it('reports stale jobs when alerts are enabled without notification config', function () {
    config()->set('deck.alerts.enabled', true);
    config()->set('deck.alerts.stale_jobs', [
        'App\\Jobs\\AlertJob' => ['max_age_hours' => 1],
    ]);

    createDeckStat([
        'job_class' => 'App\\Jobs\\AlertJob',
        'last_finished_at' => now()->subDays(3),
    ]);

    $this->artisan(CheckAlertsCommand::class)
        ->expectsOutputToContain('App\\Jobs\\AlertJob')
        ->assertSuccessful();
});
