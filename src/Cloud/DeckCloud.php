<?php

namespace Deck\Deck\Cloud;

use Deck\Deck\Support\DeckInstallation;
use Illuminate\Support\Str;

class DeckCloud
{
    public const string WorkersIngestPath = '/api/v1/ingest/workers';

    public const string EventsIngestPath = '/api/v1/ingest/events';

    public const string CommandsPullPath = '/api/v1/agent/commands';

    public const string CommandsAckPath = '/api/v1/agent/commands/ack';

    /**
     * Deck Cloud is strictly opt-in. When this returns false, the package must not
     * open outbound HTTP connections to Deck Cloud (or any remote URL).
     */
    public static function isEnabled(): bool
    {
        if (! config('deck.cloud.enabled', false)) {
            return false;
        }

        return filled(config('deck.cloud.url')) && filled(config('deck.cloud.api_key'));
    }

    public static function workersEnabled(): bool
    {
        return static::isEnabled() && config('deck.cloud.workers.enabled', true);
    }

    public static function commandsEnabled(): bool
    {
        return static::isEnabled() && config('deck.cloud.commands.enabled', true);
    }

    public static function eventsEnabled(): bool
    {
        return static::isEnabled() && config('deck.cloud.events.enabled', true);
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('deck.cloud.url'), '/');
    }

    public static function syncIntervalSeconds(): int
    {
        return max(1, (int) config('deck.cloud.workers.interval_seconds', 30));
    }

    /**
     * @return array{project: string, environment: string}
     */
    public static function installationIdentity(): array
    {
        return [
            'project' => static::slug(DeckInstallation::project()),
            'environment' => static::slug(DeckInstallation::environment()),
        ];
    }

    public static function slug(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'laravel';
    }
}
