<?php

use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckInstallation;
use TorMorten\Deck\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function deckProject(): string
{
    return DeckInstallation::project();
}

function deckEnvironment(): string
{
    return DeckInstallation::environment();
}

function createDeckStat(array $attributes = []): JobClassStat
{
    return JobClassStat::query()->create(array_merge([
        'project' => deckProject(),
        'environment' => deckEnvironment(),
        'job_class' => 'App\\Jobs\\ExampleJob',
        'last_status' => JobExecutionStatus::Completed,
        'last_started_at' => now()->subMinute(),
        'last_finished_at' => now(),
        'last_duration_ms' => 120,
        'success_count' => 1,
        'failure_count' => 0,
    ], $attributes));
}

function createDeckExecution(array $attributes = []): JobExecution
{
    return JobExecution::query()->create(array_merge([
        'project' => deckProject(),
        'environment' => deckEnvironment(),
        'uuid' => (string) str()->uuid(),
        'job_class' => 'App\\Jobs\\ExampleJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobExecutionStatus::Completed,
        'attempt' => 1,
        'tags' => null,
        'started_at' => now()->subSeconds(30),
        'finished_at' => now(),
        'duration_ms' => 250,
        'exception_class' => null,
        'exception_message' => null,
        'exception_trace' => null,
        'created_at' => now(),
    ], $attributes));
}
