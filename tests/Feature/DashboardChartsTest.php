<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Horizon\HorizonSnapshot;

it('renders job volume and duration charts on the overview', function () {
    createDeckExecution([
        'started_at' => now()->subHours(2),
        'duration_ms' => 2_000,
        'status' => JobExecutionStatus::Completed,
    ]);

    $response = $this->get(route('deck.index'));

    $response->assertOk();
    $response->assertSee('Job volume');
    $response->assertSee('Average duration');
    $response->assertSee('Queue pressure');
    $response->assertSee('Pressure index');
    $response->assertSee('deck-line-chart', false);
    $response->assertSee('deckLineChart', false);
    $response->assertSee('jobs executed', false);
    $response->assertDontSee('No executions in this period.');
});

it('shows queue pressure from horizon on the overview when horizon is installed', function () {
    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(true);
    $snapshot->shouldReceive('summary')->andReturn([
        'status' => 'running',
        'processes' => 3,
        'jobs_per_minute' => 12,
        'paused_masters' => 0,
        'wait' => ['redis:default' => 0],
    ]);
    $snapshot->shouldReceive('workload')->andReturn([
        ['name' => 'default', 'length' => 5, 'wait' => 2, 'processes' => 2],
    ]);
    $snapshot->shouldReceive('supervisors')->andReturn([]);

    $this->app->instance(HorizonSnapshot::class, $snapshot);

    $response = $this->get(route('deck.index'));

    $response->assertOk();
    $response->assertSee('Queue pressure');
    $response->assertSee('Pressure index');
    $response->assertSee('Live from Horizon');
    $response->assertDontSee('Horizon workers');
});
