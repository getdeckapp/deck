<?php

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use TorMorten\Deck\Deck;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Exceptions\JobCancelledException;
use TorMorten\Deck\Facades\Deck as DeckFacade;
use TorMorten\Deck\Livewire\Dashboard;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckInstallation;
use TorMorten\Deck\Support\JobCancellation;
use TorMorten\Deck\Tests\Fixtures\CancellableOnlyTestJob;
use TorMorten\Deck\Tests\Fixtures\SlowCancellableTestJob;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('stores a cancel flag in cache', function () {
    JobCancellation::cancel('test-uuid');

    expect(JobCancellation::isCancelled('test-uuid'))->toBeTrue();
});

it('clears a cancel flag from cache', function () {
    JobCancellation::cancel('test-uuid');
    JobCancellation::clear('test-uuid');

    expect(JobCancellation::isCancelled('test-uuid'))->toBeFalse();
});

it('cancels by uuid through the deck facade', function () {
    DeckFacade::cancel('facade-uuid');

    expect(DeckFacade::isCancelled('facade-uuid'))->toBeTrue();
});

it('cancels a job cooperatively when flagged during processing', function () {
    Event::listen(JobProcessing::class, function (JobProcessing $event): void {
        if ($uuid = JobCancellation::uuidFromJob($event->job)) {
            JobCancellation::cancel($uuid);
        }
    });

    try {
        SlowCancellableTestJob::dispatch();
    } catch (JobCancelledException) {
        //
    }

    $execution = JobExecution::query()
        ->where('job_class', SlowCancellableTestJob::class)
        ->latest('id')
        ->first();

    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe(JobExecutionStatus::Cancelled);
});

it('returns false when cancelling a non-running execution', function () {
    SuccessfulTestJob::dispatch();

    $execution = JobExecution::query()
        ->where('job_class', SuccessfulTestJob::class)
        ->latest('id')
        ->first();

    expect(app(Deck::class)->cancelExecution($execution->uuid, $execution->attempt))->toBeFalse();
});

it('cancels a running execution by uuid', function () {
    $execution = JobExecution::query()->create([
        'project' => DeckInstallation::project(),
        'environment' => DeckInstallation::environment(),
        'uuid' => (string) Str::uuid(),
        'job_class' => SlowCancellableTestJob::class,
        'connection' => 'sync',
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
        'attempt' => 1,
        'started_at' => now(),
    ]);

    expect(app(Deck::class)->cancelExecution($execution->uuid, $execution->attempt))->toBeTrue()
        ->and(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('cancels at middleware entry when only cancellable middleware is used', function () {
    Event::listen(JobProcessing::class, function (JobProcessing $event): void {
        if ($uuid = JobCancellation::uuidFromJob($event->job)) {
            JobCancellation::cancel($uuid);
        }
    });

    try {
        CancellableOnlyTestJob::dispatch();
    } catch (JobCancelledException) {
        //
    }

    $execution = JobExecution::query()
        ->where('job_class', CancellableOnlyTestJob::class)
        ->latest('id')
        ->first();

    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe(JobExecutionStatus::Cancelled)
        ->and(JobCancellation::isCancelled($execution->uuid))->toBeFalse();
});

it('requests cancellation from the dashboard livewire component', function () {
    $execution = JobExecution::query()->create([
        'project' => DeckInstallation::project(),
        'environment' => DeckInstallation::environment(),
        'uuid' => (string) Str::uuid(),
        'job_class' => SlowCancellableTestJob::class,
        'connection' => 'sync',
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
        'attempt' => 1,
        'started_at' => now(),
    ]);

    Livewire::test(Dashboard::class)
        ->call('cancelExecution', $execution->uuid, $execution->attempt);

    expect(JobCancellation::isCancelled($execution->uuid))->toBeTrue();
});

it('does not list running jobs on the dashboard overview', function () {
    JobExecution::query()->create([
        'project' => DeckInstallation::project(),
        'environment' => DeckInstallation::environment(),
        'uuid' => (string) Str::uuid(),
        'job_class' => SlowCancellableTestJob::class,
        'connection' => 'sync',
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
        'attempt' => 1,
        'started_at' => now(),
    ]);

    $this->get(route('deck.index'))
        ->assertOk()
        ->assertDontSee('No jobs are running right now')
        ->assertDontSee('Latest activity');
});
