<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;

it('scopes executions to the current installation', function () {
    createDeckExecution(['job_class' => 'App\\Jobs\\ScopedExecution']);

    JobExecution::query()->create([
        'project' => 'other-app',
        'environment' => deckEnvironment(),
        'uuid' => (string) str()->uuid(),
        'job_class' => 'App\\Jobs\\Other',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobExecutionStatus::Completed,
        'attempt' => 1,
        'started_at' => now(),
        'finished_at' => now(),
        'created_at' => now(),
    ]);

    expect(JobExecution::query()->forInstallation()->count())->toBe(1);
});

it('detects long running executions from config threshold', function () {
    config()->set('deck.long_running_threshold_seconds', 300);

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subMinutes(6),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    expect($execution->isLongRunning())->toBeTrue();
});

it('does not flag recently started running jobs as long running', function () {
    config()->set('deck.long_running_threshold_seconds', 300);

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Running,
        'started_at' => now()->subSeconds(30),
        'finished_at' => null,
        'duration_ms' => null,
    ]);

    expect($execution->isLongRunning())->toBeFalse();
});

it('does not flag completed executions as long running', function () {
    config()->set('deck.long_running_threshold_seconds', 60);

    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Completed,
        'started_at' => now()->subHour(),
    ]);

    expect($execution->isLongRunning())->toBeFalse();
});

it('casts tags and status', function () {
    $execution = createDeckExecution([
        'status' => JobExecutionStatus::Failed,
        'tags' => ['billing', 'invoice'],
    ]);

    $execution = $execution->fresh();

    expect($execution->status)->toBe(JobExecutionStatus::Failed)
        ->and($execution->tags)->toBe(['billing', 'invoice']);
});
