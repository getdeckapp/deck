<?php

use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Events\CloudEventBuffer;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Recorders\DispatchingJobExecutionRecorder;
use Deck\Deck\Recorders\HttpJobExecutionRecorder;
use Deck\Deck\Recording\QueuedJobMetadata;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    enableDeckCloudForTests();
    config()->set('deck.cloud.events.enabled', true);
    config()->set('deck.cloud.events.batch_size', 1);

    app()->forgetInstance(JobExecutionRecorder::class);
    app()->forgetInstance(DispatchingJobExecutionRecorder::class);
    app()->forgetInstance(HttpJobExecutionRecorder::class);
    app()->forgetInstance(CloudEventBuffer::class);
});

it('records through the dispatching recorder', function () {
    expect(app(JobExecutionRecorder::class))->toBeInstanceOf(DispatchingJobExecutionRecorder::class);
});

it('posts job execution events to deck cloud', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/events' => Http::response(['accepted' => 1, 'duplicates' => 0], 202),
    ]);

    $uuid = (string) str()->uuid();

    app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: $uuid,
            jobClass: 'App\\Jobs\\SyncInvoices',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: ['billing'],
        ),
        project: 'Billing API',
        environment: 'production',
        status: JobExecutionStatus::Completed,
        startedAt: Carbon::parse('2026-05-20T10:00:00Z'),
        finishedAt: Carbon::parse('2026-05-20T10:00:05Z'),
        durationMs: 5000,
    ));

    expect(JobExecution::query()->where('uuid', $uuid)->exists())->toBeTrue();

    Http::assertSent(function ($request) use ($uuid) {
        $data = $request->data();

        return $request->url() === 'https://cloud.deck.test/api/v1/ingest/events'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && ($data['events'][0]['uuid'] ?? null) === $uuid
            && ($data['events'][0]['project'] ?? null) === 'billing-api'
            && ($data['events'][0]['status'] ?? null) === 'completed'
            && ($data['events'][0]['duration_ms'] ?? null) === 5000;
    });
});

it('does not include finished fields for running events', function () {
    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/events' => Http::response(['accepted' => 1, 'duplicates' => 0], 202),
    ]);

    app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: (string) str()->uuid(),
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

    Http::assertSent(function ($request) {
        $event = $request->data()['events'][0] ?? [];

        return ! array_key_exists('finished_at', $event)
            && ! array_key_exists('duration_ms', $event);
    });
});

it('does not post events when cloud event sync is disabled', function () {
    config()->set('deck.cloud.events.enabled', false);
    app()->forgetInstance(JobExecutionRecorder::class);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/events' => Http::response(['accepted' => 1, 'duplicates' => 0], 202),
    ]);

    app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: (string) str()->uuid(),
            jobClass: 'App\\Jobs\\Example',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: null,
        ),
        project: deckProject(),
        environment: deckEnvironment(),
        status: JobExecutionStatus::Completed,
        startedAt: Carbon::now()->subSecond(),
        finishedAt: Carbon::now(),
        durationMs: 1000,
    ));

    Http::assertNothingSent();
});

it('does not open outbound http for events when cloud is disabled', function () {
    config()->set('deck.cloud.enabled', false);
    Http::fake();

    expect(DeckCloud::eventsEnabled())->toBeFalse();

    app(HttpJobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: (string) str()->uuid(),
            jobClass: 'App\\Jobs\\Example',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: null,
        ),
        project: deckProject(),
        environment: deckEnvironment(),
        status: JobExecutionStatus::Completed,
        startedAt: Carbon::now()->subSecond(),
        finishedAt: Carbon::now(),
        durationMs: 1000,
    ));

    Http::assertNothingSent();
});

it('batches live events before posting to deck cloud', function () {
    config()->set('deck.cloud.events.batch_size', 3);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/events' => Http::response(['accepted' => 3, 'duplicates' => 0], 202),
    ]);

    $recorder = app(JobExecutionRecorder::class);

    for ($i = 0; $i < 3; $i++) {
        $recorder->record(new JobExecutionRecord(
            metadata: new QueuedJobMetadata(
                uuid: (string) str()->uuid(),
                jobClass: 'App\\Jobs\\Example',
                connection: 'redis',
                queue: 'default',
                attempt: 1,
                tags: null,
            ),
            project: deckProject(),
            environment: deckEnvironment(),
            status: JobExecutionStatus::Completed,
            startedAt: Carbon::now()->subSecond(),
            finishedAt: Carbon::now(),
            durationMs: 1000,
        ));
    }

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        return count($request->data()['events'] ?? []) === 3;
    });
});
