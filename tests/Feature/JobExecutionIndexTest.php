<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobExecutionIndex;
use Livewire\Livewire;

it('filters activity by queue', function () {
    createDeckExecution(['queue' => 'high', 'job_class' => 'App\\Jobs\\HighQueueJob']);
    createDeckExecution(['queue' => 'default', 'job_class' => 'App\\Jobs\\DefaultQueueJob']);

    Livewire::test(JobExecutionIndex::class)
        ->set('queue', 'high')
        ->assertSee('HighQueueJob')
        ->assertDontSee('DefaultQueueJob');
});

it('filters activity by search term', function () {
    $uuid = 'searchable-uuid-1234';

    createDeckExecution([
        'uuid' => $uuid,
        'job_class' => 'App\\Jobs\\SearchTargetJob',
    ]);
    createDeckExecution(['job_class' => 'App\\Jobs\\OtherJob']);

    Livewire::test(JobExecutionIndex::class)
        ->set('search', 'SearchTarget')
        ->assertSee('SearchTargetJob')
        ->assertDontSee('OtherJob');
});

it('filters activity by status via query string', function () {
    createDeckExecution([
        'job_class' => 'App\\Jobs\\RunningJob',
        'status' => JobExecutionStatus::Running,
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    $this->get(route('deck.activity.index', ['status' => 'running']))
        ->assertOk()
        ->assertSee('RunningJob');
});
