<?php

use Illuminate\Support\Str;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckInstallation;
use TorMorten\Deck\Tests\Fixtures\FailingTestJob;
use TorMorten\Deck\Tests\Fixtures\SuccessfulTestJob;

it('records a successful job execution and class stats', function () {
    SuccessfulTestJob::dispatch();

    $jobClass = SuccessfulTestJob::class;

    $stat = JobClassStat::query()
        ->where('project', DeckInstallation::project())
        ->where('environment', DeckInstallation::environment())
        ->where('job_class', $jobClass)
        ->firstOrFail();

    expect($stat->last_status)->toBe(JobExecutionStatus::Completed)
        ->and($stat->last_started_at)->not->toBeNull()
        ->and($stat->last_finished_at)->not->toBeNull()
        ->and($stat->success_count)->toBe(1)
        ->and($stat->failure_count)->toBe(0);

    $execution = JobExecution::query()->where('job_class', $jobClass)->first();

    expect($execution)->not->toBeNull()
        ->and($execution->status)->toBe(JobExecutionStatus::Completed)
        ->and($execution->finished_at)->not->toBeNull()
        ->and($execution->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('records a failed job execution and class stats', function () {
    try {
        FailingTestJob::dispatch();
    } catch (RuntimeException) {
        //
    }

    $jobClass = FailingTestJob::class;

    $stat = JobClassStat::query()
        ->where('project', DeckInstallation::project())
        ->where('environment', DeckInstallation::environment())
        ->where('job_class', $jobClass)
        ->firstOrFail();

    expect($stat->last_status)->toBe(JobExecutionStatus::Failed)
        ->and($stat->failure_count)->toBe(1)
        ->and($stat->success_count)->toBe(0);

    $execution = JobExecution::query()->where('job_class', $jobClass)->first();

    expect($execution->status)->toBe(JobExecutionStatus::Failed)
        ->and($execution->exception_class)->toBe(RuntimeException::class)
        ->and($execution->exception_message)->toContain('Deck test failure')
        ->and($execution->exception_trace)->not->toBeEmpty()
        ->and($execution->exception_trace)->toContain('FailingTestJob');
});

it('prunes old job executions', function () {
    JobExecution::query()->create([
        'project' => DeckInstallation::project(),
        'environment' => DeckInstallation::environment(),
        'uuid' => (string) Str::uuid(),
        'job_class' => SuccessfulTestJob::class,
        'connection' => 'sync',
        'queue' => 'default',
        'status' => JobExecutionStatus::Completed,
        'attempt' => 1,
        'started_at' => now()->subDays(100),
        'finished_at' => now()->subDays(100),
        'duration_ms' => 10,
        'created_at' => now()->subDays(100),
    ]);

    $this->artisan('deck:prune')->assertSuccessful();

    expect(JobExecution::query()->count())->toBe(0);
});
