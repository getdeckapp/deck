<?php

use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;

it('renders the overview dashboard', function () {
    JobClassStat::query()->create([
        'project' => 'test',
        'environment' => 'testing',
        'job_class' => 'App\\Jobs\\ExampleJob',
        'last_status' => JobExecutionStatus::Completed,
        'last_finished_at' => now(),
        'success_count' => 3,
        'failure_count' => 1,
    ]);

    JobExecution::query()->create([
        'project' => 'test',
        'environment' => 'testing',
        'uuid' => (string) str()->uuid(),
        'job_class' => 'App\\Jobs\\ExampleJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobExecutionStatus::Running,
        'attempt' => 1,
        'started_at' => now()->subMinutes(10),
    ]);

    $response = $this->get(route('deck.index'));

    $response->assertOk();
    $response->assertSee('Overview');
    $response->assertSee('Running now');
    $response->assertSee('ExampleJob');
});

it('renders the activity feed with status filter', function () {
    JobExecution::query()->create([
        'project' => 'test',
        'environment' => 'testing',
        'uuid' => (string) str()->uuid(),
        'job_class' => 'App\\Jobs\\FailedJob',
        'connection' => 'redis',
        'queue' => 'high',
        'status' => JobExecutionStatus::Failed,
        'attempt' => 1,
        'started_at' => now(),
        'exception_message' => 'Something went wrong',
    ]);

    $response = $this->get(route('deck.activity.index', ['status' => 'failed']));

    $response->assertOk();
    $response->assertSee('Activity');
    $response->assertSee('FailedJob');
    $response->assertSee('Something went wrong');
});

it('renders job classes index at dedicated route', function () {
    $response = $this->get(route('deck.classes.index'));

    $response->assertOk();
    $response->assertSee('Job classes');
});
