<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Enums\QueueBusynessLevel;
use Deck\Deck\Horizon\HorizonSnapshot;
use Deck\Deck\Presentation\QueueBusyness;

it('scores busy queues from deck execution history', function () {
    createDeckExecution([
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
    ]);

    createDeckExecution([
        'queue' => 'default',
        'status' => JobExecutionStatus::Completed,
        'started_at' => now()->subMinutes(10),
        'finished_at' => now()->subMinutes(9),
    ]);

    createDeckExecution([
        'queue' => 'default',
        'status' => JobExecutionStatus::Completed,
        'started_at' => now()->subMinutes(20),
        'finished_at' => now()->subMinutes(19),
    ]);

    $assessment = app(QueueBusyness::class)->assess();

    expect($assessment['source'])->toBe('deck')
        ->and($assessment['score'])->toBeGreaterThan(0)
        ->and($assessment['queues'])->not->toBeEmpty()
        ->and($assessment['level'])->not->toBe(QueueBusynessLevel::Idle);
});

it('uses horizon workload when horizon is available', function () {
    $snapshot = Mockery::mock(HorizonSnapshot::class);
    $snapshot->shouldReceive('isAvailable')->andReturn(true);
    $snapshot->shouldReceive('summary')->andReturn([
        'status' => 'running',
        'processes' => 4,
        'jobs_per_minute' => 40,
        'paused_masters' => 0,
        'wait' => [],
    ]);
    $snapshot->shouldReceive('workload')->andReturn([
        ['name' => 'default', 'length' => 120, 'wait' => 30, 'processes' => 2],
    ]);

    $this->app->instance(HorizonSnapshot::class, $snapshot);

    $assessment = app(QueueBusyness::class)->assess();

    expect($assessment['source'])->toBe('horizon')
        ->and($assessment['score'])->toBeGreaterThan(30)
        ->and($assessment['queues'][0]['name'])->toBe('default');
});
