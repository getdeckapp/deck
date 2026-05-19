<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobExecutionIndex;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

it('opens a confirmation modal before retrying a failed job', function () {
    Bus::fake();

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'job_class' => SuccessfulTestJob::class,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->call('requestConfirmation', 'retryExecution', [$execution->uuid, $execution->attempt], 'Retry failed job', 'Test message', 'Retry', 'Retrying…', 'primary')
        ->assertSet('pendingConfirmation.title', 'Retry failed job')
        ->assertSee('Test message')
        ->call('executeConfirmedAction')
        ->assertSet('pendingConfirmation', null)
        ->assertOk();

    Bus::assertDispatched(SuccessfulTestJob::class);
});

it('clears the confirmation modal when cancelled', function () {
    createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'failed',
    ]);

    Livewire::test(JobExecutionIndex::class)
        ->call('requestConfirmation', 'retryExecution', ['uuid', 1], 'Retry failed job', 'Test message')
        ->call('cancelConfirmation')
        ->assertSet('pendingConfirmation', null);
});
