<?php

use Deck\Deck\Support\HorizonSnapshot;

it('renders the workers and queues page', function () {
    $response = $this->get(route('deck.workers.index'));

    $response->assertOk();
    $response->assertSee('Workers');
    $response->assertSee('No queue activity recorded yet.');
});

it('shows horizon workload on the workers page when horizon is installed', function () {
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
    $snapshot->shouldReceive('masters')->andReturn([
        ['name' => 'macbook', 'status' => 'running', 'supervisors' => 1, 'processes' => 3],
    ]);
    $snapshot->shouldReceive('supervisors')->andReturn([
        [
            'name' => 'macbook:supervisor-1',
            'master' => 'macbook',
            'status' => 'running',
            'pid' => 1234,
            'processes' => 3,
            'queues' => [['name' => 'redis:default', 'processes' => 3]],
        ],
    ]);

    $this->app->instance(HorizonSnapshot::class, $snapshot);

    $response = $this->get(route('deck.workers.index'));

    $response->assertOk();
    $response->assertSee('Horizon workers');
    $response->assertSee('Supervisors');
    $response->assertSee('macbook:supervisor-1');
});
