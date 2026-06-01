<?php

use Deck\Deck\Presentation\DeckPolling;

it('polls the dashboard faster while jobs are running', function () {
    config()->set('deck.poll.dashboard_seconds', 8);
    config()->set('deck.poll.dashboard_running_seconds', 2);

    expect(DeckPolling::dashboardSeconds(0))->toBe(8)
        ->and(DeckPolling::dashboardSeconds(3))->toBe(2);
});

it('respects configured worker and execution poll intervals', function () {
    config()->set('deck.poll.workers_seconds', 5);
    config()->set('deck.poll.executions_seconds', 1);

    expect(DeckPolling::workersSeconds())->toBe(5)
        ->and(DeckPolling::executionsSeconds())->toBe(1);
});

it('polls the activity feed faster while jobs are running', function () {
    config()->set('deck.poll.activity_seconds', 5);
    config()->set('deck.poll.executions_seconds', 2);

    expect(DeckPolling::activitySeconds(false))->toBe(5)
        ->and(DeckPolling::activitySeconds(true))->toBe(2);
});
