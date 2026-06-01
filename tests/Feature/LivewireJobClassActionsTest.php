<?php

use Deck\Deck\Blocking\JobClassBlock;
use Deck\Deck\Cancellation\JobCancellation;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobClassShow;
use Livewire\Livewire;

it('cancels all running executions for a class via livewire', function () {
    $jobClass = 'App\\Jobs\\LivewireBulkCancelJob';

    $running = createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Livewire::test(JobClassShow::class, ['jobClass' => $jobClass])
        ->call('cancelAllRunning')
        ->assertOk();

    expect(JobCancellation::isCancelled($running->uuid))->toBeTrue();
});

it('blocks and unblocks a class via livewire', function () {
    $jobClass = 'App\\Jobs\\LivewireBlockJob';

    Livewire::test(JobClassShow::class, ['jobClass' => $jobClass])
        ->call('confirmBlockClass', '1h')
        ->call('executeConfirmedAction')
        ->assertOk();

    expect(JobClassBlock::isBlocked($jobClass))->toBeTrue();

    Livewire::test(JobClassShow::class, ['jobClass' => $jobClass])
        ->call('unblockClass')
        ->assertOk();

    expect(JobClassBlock::isBlocked($jobClass))->toBeFalse();
});

it('shows cancel all and block actions on the class page', function () {
    $jobClass = 'App\\Jobs\\ActionableJob';

    createDeckExecution([
        'job_class' => $jobClass,
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    $this->get(route('deck.classes.show', ['jobClass' => $jobClass]))
        ->assertOk()
        ->assertSee('Cancel all (1)')
        ->assertSee('Block job');
});
