<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\JobClassIndex;
use Deck\Deck\Models\JobClassStat;
use Livewire\Livewire;

it('filters job classes by search term', function () {
    createDeckStat(['job_class' => 'App\\Jobs\\BillingJob']);
    createDeckStat(['job_class' => 'App\\Jobs\\ShippingJob']);

    Livewire::test(JobClassIndex::class)
        ->set('search', 'Billing')
        ->assertSee('BillingJob')
        ->assertDontSee('ShippingJob');
});

it('filters job classes by last status', function () {
    createDeckStat([
        'job_class' => 'App\\Jobs\\FailedJob',
        'last_status' => JobExecutionStatus::Failed,
    ]);
    createDeckStat([
        'job_class' => 'App\\Jobs\\HealthyJob',
        'last_status' => JobExecutionStatus::Completed,
    ]);

    Livewire::test(JobClassIndex::class)
        ->call('setStatus', 'failed')
        ->assertSee('FailedJob')
        ->assertDontSee('HealthyJob');
});

it('renders job classes from the current installation only', function () {
    createDeckStat(['job_class' => 'App\\Jobs\\VisibleJob']);

    JobClassStat::query()->create([
        'project' => 'other-app',
        'environment' => deckEnvironment(),
        'job_class' => 'App\\Jobs\\HiddenJob',
        'success_count' => 0,
        'failure_count' => 0,
    ]);

    $this->get(route('deck.classes.index'))
        ->assertOk()
        ->assertSee('VisibleJob')
        ->assertDontSee('HiddenJob');
});
