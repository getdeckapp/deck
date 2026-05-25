<?php

namespace Deck\Deck\Dispatch;

use Deck\Deck\Enums\DispatchGroupSource;
use Illuminate\Contracts\Queue\Job as QueueJobContract;

class DispatchLineage
{
    private static ?ActiveJobScope $active = null;

    public static function enabled(): bool
    {
        return (bool) config('deck.lifecycle.parent_job', true)
            || (bool) config('deck.dispatch_groups.lineage', true);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function scopeFromJob(QueueJobContract $job, callable $callback): mixed
    {
        if (! self::enabled()) {
            return $callback();
        }

        $previous = self::$active;
        self::$active = ActiveJobScope::fromQueueJob($job);

        try {
            return $callback();
        } finally {
            self::$active = $previous;
        }
    }

    public static function activeJobUuid(): ?string
    {
        return self::$active?->uuid;
    }

    public static function activeJobClass(): ?string
    {
        return self::$active?->class;
    }

    /**
     * @return array{id: string, source: DispatchGroupSource}|null
     */
    public static function inheritedDispatchGroup(): ?array
    {
        if (! (bool) config('deck.dispatch_groups.lineage', true)) {
            return null;
        }

        $group = self::$active?->dispatchGroup;

        if ($group === null) {
            return null;
        }

        return [
            'id' => $group['id'],
            'source' => DispatchGroupSource::Lineage,
        ];
    }

    /**
     * @param  array<string, mixed>  $deck
     */
    public static function parseDeckPayload(array $deck): ActiveJobScope
    {
        $group = is_array($deck['dispatch_group'] ?? null) ? $deck['dispatch_group'] : [];

        return new ActiveJobScope(
            uuid: '',
            class: '',
            dispatchGroup: is_string($group['id'] ?? null) && $group['id'] !== ''
                ? [
                    'id' => (string) $group['id'],
                    'source' => is_string($group['source'] ?? null) ? (string) $group['source'] : null,
                ]
                : null,
        );
    }
}

readonly class ActiveJobScope
{
    /**
     * @param  array{id: string, source: string|null}|null  $dispatchGroup
     */
    public function __construct(
        public string $uuid,
        public string $class,
        public ?array $dispatchGroup,
    ) {}

    public static function fromQueueJob(QueueJobContract $job): self
    {
        $payload = $job->payload();
        $deck = is_array($payload['deck'] ?? null) ? $payload['deck'] : [];

        $uuid = is_string($payload['uuid'] ?? null) ? $payload['uuid'] : '';
        $class = is_string($payload['displayName'] ?? null) ? $payload['displayName'] : '';

        $group = is_array($deck['dispatch_group'] ?? null) ? $deck['dispatch_group'] : [];

        return new self(
            uuid: $uuid,
            class: $class,
            dispatchGroup: is_string($group['id'] ?? null) && $group['id'] !== ''
                ? [
                    'id' => (string) $group['id'],
                    'source' => is_string($group['source'] ?? null) ? (string) $group['source'] : null,
                ]
                : null,
        );
    }
}
