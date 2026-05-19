<?php

namespace Deck\Deck\Cloud;

use Deck\Deck\Support\DeckInstallation;
use Illuminate\Support\Str;

class DeckCloud
{
    public static function isEnabled(): bool
    {
        if (! config('deck.cloud.enabled')) {
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
