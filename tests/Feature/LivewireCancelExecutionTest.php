<?php

use Livewire\Livewire;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\JobExecutionIndex;
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
