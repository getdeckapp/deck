<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobClassShow;
use Livewire\Livewire;

it('renders the job class detail page', function () {
    $jobClass = 'App\\Jobs\\DetailJob';

    createDeckStat(['job_class' => $jobClass]);
    createDeckExecution(['job_class' => $jobClass]);

    $this->get(route('deck.classes.show', ['jobClass' => $jobClass]))
        ->assertOk()
        ->assertSee('DetailJob')
        ->assertSee('Executions');
});

it('filters executions by status on the class page', function () {
    $jobClass = 'App\\Jobs\\FilterableJob';

    createDeckStat(['job_class' => $jobClass]);
    createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Failed,
        'exception_message' => 'Boom',
    ]);
    createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Completed,
    ]);

    Livewire::test(JobClassShow::class, ['jobClass' => $jobClass])
        ->call('setStatus', 'failed')
        ->assertSee('failed')
        ->assertSee('Boom');
});
