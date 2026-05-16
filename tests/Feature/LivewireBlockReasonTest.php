<?php

use Livewire\Livewire;
use TorMorten\Deck\Livewire\JobClassShow;
use TorMorten\Deck\Support\JobClassBlock;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

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
