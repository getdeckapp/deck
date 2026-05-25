<?php

namespace Deck\Deck\Dispatch;

use Deck\Deck\Data\ObservabilitySnapshot;
use Deck\Deck\Enums\DispatchGroupSource;
use Illuminate\Support\Carbon;

class DeckObservability
{
    public static function enabled(): bool
    {
        return DispatchGroup::enabled() || (bool) config('deck.lifecycle.enabled', true);
    }

    public static function snapshotForDispatch(): ObservabilitySnapshot
    {
        if (! self::enabled()) {
            return new ObservabilitySnapshot;
        }

        $group = DispatchGroup::resolve();
        $parentUuid = null;
        $parentClass = null;

        if ((bool) config('deck.lifecycle.parent_job', true)) {
            $parentUuid = DispatchLineage::activeJobUuid();
            $parentClass = DispatchLineage::activeJobClass();

            if ($parentUuid === '') {
                $parentUuid = null;
                $parentClass = null;
            }
        }

        return new ObservabilitySnapshot(
            dispatchedAt: (bool) config('deck.lifecycle.enabled', true) ? Carbon::now() : null,
            dispatchGroupId: $group['id'] ?? null,
            dispatchGroupSource: $group['source'] ?? null,
            batchId: null,
            parentJobUuid: $parentUuid,
            parentJobClass: $parentClass,
            dispatchOrigin: DispatchOrigin::resolve(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stampQueuePayload(array $payload): array
    {
        if (! self::enabled()) {
            return $payload;
        }

        $snapshot = self::snapshotForDispatch();

        if ($payload['batchId'] ?? null) {
            $snapshot = new ObservabilitySnapshot(
                dispatchedAt: $snapshot->dispatchedAt,
                dispatchGroupId: $snapshot->dispatchGroupId,
                dispatchGroupSource: $snapshot->dispatchGroupSource,
                batchId: is_string($payload['batchId']) ? $payload['batchId'] : null,
                parentJobUuid: $snapshot->parentJobUuid,
                parentJobClass: $snapshot->parentJobClass,
                dispatchOrigin: $snapshot->dispatchOrigin,
            );
        }

        $fragment = $snapshot->deckPayloadFragment();

        if ($fragment === null) {
            return $payload;
        }

        $payload['deck'] = array_replace_recursive(
            is_array($payload['deck'] ?? null) ? $payload['deck'] : [],
            $fragment,
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $deck
     */
    public static function snapshotFromDeckPayload(array $deck, ?string $batchId = null): ObservabilitySnapshot
    {
        $group = is_array($deck['dispatch_group'] ?? null) ? $deck['dispatch_group'] : [];
        $parent = is_array($deck['parent_job'] ?? null) ? $deck['parent_job'] : [];
        $origin = is_array($deck['dispatch_origin'] ?? null) ? $deck['dispatch_origin'] : null;

        $dispatchedAt = null;

        if (is_string($deck['dispatched_at'] ?? null) && $deck['dispatched_at'] !== '') {
            $dispatchedAt = Carbon::parse($deck['dispatched_at']);
        }

        $source = is_string($group['source'] ?? null)
            ? DispatchGroupSource::tryFrom($group['source'])
            : null;

        return new ObservabilitySnapshot(
            dispatchedAt: $dispatchedAt,
            dispatchGroupId: is_string($group['id'] ?? null) ? $group['id'] : null,
            dispatchGroupSource: $source,
            batchId: is_string($deck['batch_id'] ?? null) ? $deck['batch_id'] : $batchId,
            parentJobUuid: is_string($parent['uuid'] ?? null) ? $parent['uuid'] : null,
            parentJobClass: is_string($parent['class'] ?? null) ? $parent['class'] : null,
            dispatchOrigin: $origin,
        );
    }
}
