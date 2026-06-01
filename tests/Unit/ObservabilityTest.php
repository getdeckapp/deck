<?php

use Deck\Deck\Cloud\Events\CloudObservabilityIngestFields;
use Deck\Deck\Data\ObservabilitySnapshot;
use Deck\Deck\Dispatch\DeckObservability;
use Deck\Deck\Dispatch\DispatchGroup;
use Deck\Deck\Enums\DispatchGroupSource;
use Illuminate\Support\Carbon;

it('builds cloud ingest fields from an observability snapshot', function (): void {
    $dispatchedAt = Carbon::parse('2026-05-25T14:02:01Z');

    $fields = CloudObservabilityIngestFields::fromSnapshot(
        new ObservabilitySnapshot(
            dispatchedAt: $dispatchedAt,
            dispatchGroupId: 'req-7f3a2b1c',
            dispatchGroupSource: DispatchGroupSource::Request,
            parentJobUuid: '550e8400-e29b-41d4-a716-446655440000',
            parentJobClass: 'App\\Jobs\\ParentJob',
            dispatchOrigin: [
                'type' => 'http',
                'method' => 'POST',
                'route' => 'orders.store',
            ],
        ),
        waitMs: 3000,
    );

    expect($fields)->toMatchArray([
        'dispatched_at' => '2026-05-25T14:02:01+00:00',
        'wait_ms' => 3000,
        'dispatch_group_id' => 'req-7f3a2b1c',
        'dispatch_group_source' => 'request',
        'parent_job_uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'parent_job_class' => 'App\\Jobs\\ParentJob',
        'dispatch_origin' => [
            'type' => 'http',
            'method' => 'POST',
            'route' => 'orders.store',
        ],
    ]);
});

it('returns an empty array when no observability data is present', function (): void {
    expect(CloudObservabilityIngestFields::fromSnapshot(null))->toBe([]);
});

it('stamps queue payloads with dispatch group metadata', function (): void {
    $payload = DeckObservability::stampQueuePayload([
        'uuid' => (string) str()->uuid(),
        'displayName' => 'App\\Jobs\\Example',
    ]);

    DispatchGroup::using('campaign-123', function () use (&$childPayload): void {
        $childPayload = DeckObservability::stampQueuePayload([
            'uuid' => (string) str()->uuid(),
            'displayName' => 'App\\Jobs\\ChildJob',
        ]);
    }, DispatchGroupSource::Manual);

    expect($payload['deck']['dispatched_at'] ?? null)->not->toBeNull()
        ->and($childPayload['deck']['dispatch_group']['id'] ?? null)->toBe('campaign-123')
        ->and($childPayload['deck']['dispatch_group']['source'] ?? null)->toBe('manual');
});

it('parses observability data from deck queue payloads', function (): void {
    $snapshot = DeckObservability::snapshotFromDeckPayload([
        'dispatched_at' => '2026-05-25T14:02:01+00:00',
        'dispatch_group' => [
            'id' => 'req-abc',
            'source' => 'request',
        ],
        'parent_job' => [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'class' => 'App\\Jobs\\ParentJob',
        ],
    ], batchId: '6ba7b810-9dad-11d1-80b4-00c04fd430c8');

    expect($snapshot->dispatchedAt?->utc()->toIso8601String())->toBe('2026-05-25T14:02:01+00:00')
        ->and($snapshot->dispatchGroupId)->toBe('req-abc')
        ->and($snapshot->dispatchGroupSource)->toBe(DispatchGroupSource::Request)
        ->and($snapshot->batchId)->toBe('6ba7b810-9dad-11d1-80b4-00c04fd430c8')
        ->and($snapshot->parentJobUuid)->toBe('550e8400-e29b-41d4-a716-446655440000')
        ->and($snapshot->parentJobClass)->toBe('App\\Jobs\\ParentJob');
});
