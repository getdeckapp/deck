<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Presentation\QueueInsights;

it('ranks busy queues from deck execution data', function () {
    createDeckExecution([
        'queue' => 'high',
        'status' => JobExecutionStatus::Running,
    ]);

    createDeckExecution([
        'queue' => 'high',
        'status' => JobExecutionStatus::Completed,
        'started_at' => now()->subMinutes(20),
        'finished_at' => now()->subMinutes(19),
    ]);

    createDeckExecution([
        'queue' => 'low',
        'status' => JobExecutionStatus::Completed,
        'started_at' => now()->subHours(3),
        'finished_at' => now()->subHours(3),
    ]);

    $queues = app(QueueInsights::class)->busyQueues();

    expect($queues->first()['queue'])->toBe('high')
        ->and($queues->first()['running'])->toBe(1);
});
