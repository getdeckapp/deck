<?php

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobClassStat;
use Deck\Deck\Models\JobExecution;

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
        'status' => JobExecutionStatus::Failed,
        'attempt' => 1,
        'started_at' => now()->subMinutes(10),
        'exception_class' => RuntimeException::class,
        'exception_message' => 'Something failed',
    ]);

    $response = $this->get(route('deck.index'));

    $response->assertOk();
    $response->assertSee('Overview');
    $response->assertSee('Recent failures');
    $response->assertSee('Job volume');
    $response->assertDontSee('Latest activity');
    $response->assertDontSee('No jobs are running right now');
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
    $response->assertSee('Jobs');
});
