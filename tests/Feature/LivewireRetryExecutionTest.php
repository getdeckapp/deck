<?php

use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\JobExecutionIndex;
use TorMorten\Deck\Livewire\JobExecutionShow;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('retries a failed execution via livewire on the activity index', function () {
    Bus::fake();

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'job_class' => SuccessfulTestJob::class,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->call('retryExecution', $execution->uuid, $execution->attempt)
        ->assertOk();

    Bus::assertDispatched(SuccessfulTestJob::class);
});

it('shows a retry button for failed jobs on the activity index', function () {
    createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->assertSee('Retry')
        ->assertSeeHtml('wire:click.stop="retryExecution');
});

it('retries a failed execution from the detail page', function () {
    Bus::fake();

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'job_class' => SuccessfulTestJob::class,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    Livewire::test(JobExecutionShow::class, $execution->activityRouteParameters())
        ->assertSee('Retry job')
        ->call('retryExecution', $execution->uuid, $execution->attempt)
        ->assertOk();

    Bus::assertDispatched(SuccessfulTestJob::class);
});

it('denies retry actions when deck auth fails', function () {
    config()->set('deck.auth', fn () => false);

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
    ]);

    $this->get(route('deck.activity.show', $execution->activityRouteParameters()))
        ->assertForbidden();
});
