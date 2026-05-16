<?php

use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Support\DeckInstallation;

it('scopes stats to the current installation', function () {
    createDeckStat(['job_class' => 'App\\Jobs\\ScopedJob']);

    JobClassStat::query()->create([
        'project' => 'other-app',
        'environment' => deckEnvironment(),
        'job_class' => 'App\\Jobs\\OtherAppJob',
        'success_count' => 0,
        'failure_count' => 0,
    ]);

    expect(JobClassStat::query()->forInstallation()->count())->toBe(1)
        ->and(JobClassStat::query()->forInstallation()->value('job_class'))->toBe('App\\Jobs\\ScopedJob');
});

it('calculates success rate from run counts', function () {
    $stat = new JobClassStat([
        'success_count' => 3,
        'failure_count' => 1,
    ]);

    expect($stat->successRate())->toBe(75.0);
});

it('returns null success rate when there are no runs', function () {
    $stat = new JobClassStat([
        'success_count' => 0,
        'failure_count' => 0,
    ]);

    expect($stat->successRate())->toBeNull();
});

it('casts last status to the enum', function () {
    $stat = createDeckStat(['last_status' => JobExecutionStatus::Failed]);

    expect($stat->fresh()->last_status)->toBe(JobExecutionStatus::Failed);
});

it('reads project and environment from config', function () {
    config()->set('deck.project', 'billing-api');
    config()->set('deck.environment', 'staging');

    expect(DeckInstallation::project())->toBe('billing-api')
        ->and(DeckInstallation::environment())->toBe('staging');
});
