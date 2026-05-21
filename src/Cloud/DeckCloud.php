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
     * Deck Cloud is opt-in via {@see config('deck.cloud.api_key')}. When an API key is
     * set, workers, commands, and events sync are enabled by default. Set
     * DECK_CLOUD_ENABLED=false to disable while keeping the key in .env.
     */
    public static function isEnabled(): bool
    {
        if (! filled(config('deck.cloud.api_key'))) {
            return false;
        }

        if (static::explicitlyDisabled()) {
            return false;
        }

        return filled(static::resolvedUrl());
    }

    public static function resolvedUrl(): string
    {
        $configured = config('deck.cloud.url');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return static::defaultUrl();
    }

    public static function defaultUrl(): string
    {
        return config('app.env') === 'local'
            ? 'http://deck.test'
            : 'https://deckapp.cloud';
    }

    private static function explicitlyDisabled(): bool
    {
        $enabled = config('deck.cloud.enabled');

        if ($enabled === null || $enabled === '') {
            return false;
        }

        return ! filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
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
        return static::resolvedUrl();
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
