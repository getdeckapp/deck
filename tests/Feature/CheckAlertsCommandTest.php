<?php

use Deck\Deck\Commands\CheckAlertsCommand;
use Deck\Deck\Data\UnprocessedQueue;
use Deck\Deck\Presentation\DeckAlertChecker;
use Deck\Deck\Presentation\UnprocessedQueueDetector;

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

it('reports unprocessed queues when alerts are enabled', function () {
    config()->set('deck.alerts.enabled', true);
    config()->set('deck.alerts.stale_jobs', []);

    $queue = new UnprocessedQueue(
        connection: 'redis',
        queue: 'billing',
        queueKey: 'redis:billing',
        pending: 9,
        workerProcesses: 0,
        horizonStatus: 'running',
        suggestion: 'Assign workers.',
    );

    $detector = Mockery::mock(UnprocessedQueueDetector::class);
    $detector->shouldReceive('detect')->andReturn(collect([$queue]));
    $this->app->instance(UnprocessedQueueDetector::class, $detector);

    expect(app(DeckAlertChecker::class)->unprocessedQueues())->toHaveCount(1);

    $this->artisan(CheckAlertsCommand::class)
        ->expectsOutputToContain('pending jobs without Horizon workers')
        ->expectsOutputToContain('redis:billing')
        ->assertSuccessful();
});
