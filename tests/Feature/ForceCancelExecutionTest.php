<?php

use Deck\Deck\Deck;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobExecutionShow;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckInstallation;
use Deck\Deck\Support\JobCancellation;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('marks a running execution cancelled when force cancelled', function () {
    $execution = JobExecution::query()->create([
        'project' => DeckInstallation::project(),
        'environment' => DeckInstallation::environment(),
        'uuid' => (string) Str::uuid(),
        'job_class' => SuccessfulTestJob::class,
        'connection' => 'sync',
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
        'attempt' => 1,
        'started_at' => now(),
    ]);

    $result = app(Deck::class)->forceCancelExecution($execution->uuid, $execution->attempt);

    expect($result)->not->toBeNull()
        ->and($execution->fresh()->status)->toBe(JobExecutionStatus::Cancelled)
        ->and(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('shows cancelling state in the execution detail view', function () {
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
        ->assertSee('Cancelling…')
        ->assertSee('Cancellation in progress');
});
