<?php

use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Recorders\DatabaseJobExecutionRecorder;
use Deck\Deck\Support\DeckInstallation;
use Deck\Deck\Support\QueuedJobMetadata;
use Illuminate\Support\Carbon;

it('does not rethrow when the database is unavailable', function () {
    $recorder = new DatabaseJobExecutionRecorder;

    config()->set('deck.database_connection', 'missing');

    $recorder->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: (string) str()->uuid(),
            jobClass: 'App\\Jobs\\Example',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: null,
        ),
        project: DeckInstallation::project(),
        environment: DeckInstallation::environment(),
        status: JobExecutionStatus::Running,
        startedAt: Carbon::now(),
    ));

    expect(true)->toBeTrue();
});

it('only persists columns required for the execution status', function () {
    $uuid = (string) str()->uuid();

    app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: $uuid,
            jobClass: 'App\\Jobs\\Example',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: null,
        ),
        project: deckProject(),
        environment: deckEnvironment(),
        status: JobExecutionStatus::Running,
        startedAt: Carbon::now(),
    ));

    $execution = JobExecution::query()->where('uuid', $uuid)->first();

    expect($execution->finished_at)->toBeNull()
        ->and($execution->exception_class)->toBeNull()
        ->and($execution->exception_message)->toBeNull();
});
