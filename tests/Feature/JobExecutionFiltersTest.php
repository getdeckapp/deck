<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobClassStat;

it('filters activity by connection and tag', function () {
    createDeckExecution([
        'job_class' => 'App\\Jobs\\TaggedJob',
        'connection' => 'redis',
        'queue' => 'high',
        'tags' => ['billing'],
        'status' => JobExecutionStatus::Completed,
    ]);

    createDeckExecution([
        'job_class' => 'App\\Jobs\\OtherJob',
        'connection' => 'sync',
        'queue' => 'default',
        'tags' => ['ops'],
        'status' => JobExecutionStatus::Completed,
    ]);

    $this->get(route('deck.activity.index', ['connection' => 'redis', 'tag' => 'billing']))
        ->assertOk()
        ->assertSee('TaggedJob')
        ->assertDontSee('OtherJob');
});

it('filters activity by blocked status', function () {
    createDeckExecution([
        'job_class' => 'App\\Jobs\\BlockedJob',
        'status' => JobExecutionStatus::Blocked,
        'finished_at' => now(),
        'duration_ms' => 0,
    ]);

    createDeckExecution([
        'job_class' => 'App\\Jobs\\CompletedJob',
        'status' => JobExecutionStatus::Completed,
    ]);

    $this->get(route('deck.activity.index', ['status' => 'blocked']))
        ->assertOk()
        ->assertSee('BlockedJob')
        ->assertDontSee('CompletedJob');
});

it('filters job classes by blocked last status', function () {
    JobClassStat::query()->create([
        'project' => 'test',
        'environment' => 'testing',
        'job_class' => 'App\\Jobs\\BlockedJob',
        'last_status' => JobExecutionStatus::Blocked,
        'last_finished_at' => now(),
    ]);

    JobClassStat::query()->create([
        'project' => 'test',
        'environment' => 'testing',
        'job_class' => 'App\\Jobs\\CompletedJob',
        'last_status' => JobExecutionStatus::Completed,
        'last_finished_at' => now(),
    ]);

    $this->get(route('deck.classes.index', ['status' => 'blocked']))
        ->assertOk()
        ->assertSee('BlockedJob')
        ->assertDontSee('CompletedJob');
});
