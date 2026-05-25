<?php

use Deck\Deck\Cloud\Events\CloudObservabilityIngestFields;
use Deck\Deck\Cloud\Events\JobExecutionIngestPayload;
use Deck\Deck\Contracts\JobExecutionRecorder;
use Deck\Deck\Data\JobExecutionRecord;
use Deck\Deck\Data\ObservabilitySnapshot;
use Deck\Deck\Dispatch\DispatchGroup;
use Deck\Deck\Enums\DispatchGroupSource;
use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Recording\QueuedJobMetadata;
use Deck\Deck\Tests\Fixtures\SuccessfulTestJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('persists observability columns when recording executions', function (): void {
    $dispatchedAt = Carbon::parse('2026-05-25T14:02:01Z');

    app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: (string) str()->uuid(),
            jobClass: 'App\\Jobs\\Example',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: null,
            observability: new ObservabilitySnapshot(
                dispatchedAt: $dispatchedAt,
                dispatchGroupId: 'req-abc',
                dispatchGroupSource: DispatchGroupSource::Request,
            ),
        ),
        project: deckProject(),
        environment: deckEnvironment(),
        status: JobExecutionStatus::Running,
        startedAt: Carbon::parse('2026-05-25T14:02:04Z'),
        waitMs: 3000,
    ));

    $execution = JobExecution::query()->first();

    expect($execution->dispatched_at?->utc()->toIso8601String())->toBe('2026-05-25T14:02:01+00:00')
        ->and($execution->wait_ms)->toBe(3000)
        ->and($execution->dispatch_group_id)->toBe('req-abc')
        ->and($execution->dispatch_group_source)->toBe(DispatchGroupSource::Request);
});

it('records dispatch group metadata when jobs are dispatched manually', function (): void {
    DispatchGroup::using('campaign-123', function (): void {
        SuccessfulTestJob::dispatch();
    }, DispatchGroupSource::Manual);

    $execution = JobExecution::query()->where('job_class', SuccessfulTestJob::class)->first();

    expect($execution)->not->toBeNull()
        ->and($execution->dispatch_group_id)->toBe('campaign-123')
        ->and($execution->dispatch_group_source)->toBe(DispatchGroupSource::Manual)
        ->and($execution->dispatched_at)->not->toBeNull()
        ->and($execution->wait_ms)->toBeGreaterThanOrEqual(0);
});

it('includes observability fields in cloud ingest payloads', function (): void {
    enableDeckCloudForTests();
    config()->set('deck.cloud.events.enabled', true);
    config()->set('deck.cloud.events.batch_size', 1);

    Http::fake([
        'https://cloud.deck.test/api/v1/ingest/events' => Http::response(['accepted' => 1, 'duplicates' => 0], 202),
    ]);

    $uuid = (string) str()->uuid();
    $dispatchedAt = Carbon::parse('2026-05-25T14:02:01Z');

    app()->forgetInstance(JobExecutionRecorder::class);

    app(JobExecutionRecorder::class)->record(new JobExecutionRecord(
        metadata: new QueuedJobMetadata(
            uuid: $uuid,
            jobClass: 'App\\Jobs\\Example',
            connection: 'redis',
            queue: 'default',
            attempt: 1,
            tags: null,
            observability: new ObservabilitySnapshot(
                dispatchedAt: $dispatchedAt,
                dispatchGroupId: 'req-abc',
                dispatchGroupSource: DispatchGroupSource::Request,
            ),
        ),
        project: deckProject(),
        environment: deckEnvironment(),
        status: JobExecutionStatus::Completed,
        startedAt: Carbon::parse('2026-05-25T14:02:04Z'),
        finishedAt: Carbon::parse('2026-05-25T14:02:31Z'),
        durationMs: 27000,
        waitMs: 3000,
    ));

    Http::assertSent(function ($request) use ($uuid): bool {
        $event = $request->data()['events'][0] ?? [];

        return ($event['uuid'] ?? null) === $uuid
            && ($event['dispatched_at'] ?? null) === '2026-05-25T14:02:01+00:00'
            && ($event['wait_ms'] ?? null) === 3000
            && ($event['dispatch_group_id'] ?? null) === 'req-abc'
            && ($event['dispatch_group_source'] ?? null) === 'request';
    });
});

it('builds ingest payloads without observability fields when absent', function (): void {
    $payload = JobExecutionIngestPayload::fromRecord(new JobExecutionRecord(
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

    expect($payload)->not->toHaveKeys([
        'dispatched_at',
        'wait_ms',
        'dispatch_group_id',
        'dispatch_group_source',
    ]);
});

it('maps stored execution observability for cloud backfill payloads', function (): void {
    $execution = createDeckExecution([
        'dispatched_at' => Carbon::parse('2026-05-25T14:02:01Z'),
        'wait_ms' => 1500,
        'dispatch_group_id' => 'req-abc',
        'dispatch_group_source' => DispatchGroupSource::Request,
    ]);

    $fields = CloudObservabilityIngestFields::fromExecution($execution);

    expect($fields)->toMatchArray([
        'dispatched_at' => '2026-05-25T14:02:01+00:00',
        'wait_ms' => 1500,
        'dispatch_group_id' => 'req-abc',
        'dispatch_group_source' => 'request',
    ]);
});
