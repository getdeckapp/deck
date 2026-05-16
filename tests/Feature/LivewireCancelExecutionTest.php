<?php

use Livewire\Livewire;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\JobExecutionIndex;
use TorMorten\Deck\Livewire\JobExecutionShow;
use TorMorten\Deck\Support\JobCancellation;

it('requests cancellation for a running execution via livewire', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->call('cancelExecution', $execution->uuid, $execution->attempt)
        ->assertOk();

    expect(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('does not flag cancellation for a completed execution', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->call('cancelExecution', $execution->uuid, $execution->attempt)
        ->assertOk();

    expect(JobCancellation::isCancelled($execution->uuid))->toBeFalse();
});

it('opens a cancel modal with cooperative and force choices', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->call('requestCancelExecutionConfirmation', $execution->uuid, $execution->attempt, false)
        ->assertSet('pendingConfirmation.title', 'Cancel running job')
        ->assertSee('Request cancel')
        ->assertSee('Force cancel')
        ->call('executeConfirmedAction', 'cancelExecution')
        ->assertSet('pendingConfirmation', null)
        ->assertOk();

    expect(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('offers only force cancel in the modal when cancellation is already pending', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinute(),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    JobCancellation::cancel($execution->uuid);

    Livewire::test(JobExecutionShow::class, [
        'uuid' => $execution->uuid,
        'attempt' => $execution->attempt,
    ])
        ->call('requestCancelExecutionConfirmation', $execution->uuid, $execution->attempt, true)
        ->assertSet('pendingConfirmation.title', 'Escalate cancellation')
        ->assertDontSee('Request cancel')
        ->assertSee('Force cancel');
});
