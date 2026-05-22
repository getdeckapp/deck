<?php

use Deck\Deck\Blocking\JobClassBlock;
use Deck\Deck\Livewire\JobClassShow;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Livewire\Livewire;

it('opens the block confirmation with a reason field', function () {
    Livewire::test(JobClassShow::class, ['jobClass' => SuccessfulTestJob::class])
        ->call('confirmBlockClass', '1h')
        ->assertSet('pendingConfirmation.method', 'blockClass')
        ->assertSet('pendingConfirmation.prompt.label', 'Reason (optional)');
});

it('stores the block reason from the confirmation modal', function () {
    Livewire::test(JobClassShow::class, ['jobClass' => SuccessfulTestJob::class])
        ->call('confirmBlockClass', '1h')
        ->set('confirmationInput', 'Pausing while we fix billing')
        ->call('executeConfirmedAction')
        ->assertSet('pendingConfirmation', null);

    $audit = JobClassBlock::audit(SuccessfulTestJob::class);

    expect($audit?->reason)->toBe('Pausing while we fix billing');
});
