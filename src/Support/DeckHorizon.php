<?php

namespace TorMorten\Deck\Support;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Horizon;

class DeckHorizon
{
    public static function isInstalled(): bool
    {
        return class_exists(Horizon::class);
    }

    public static function dashboardUrl(): ?string
    {
        if (! static::isInstalled()) {
            return null;
        }

        $path = trim((string) config('horizon.path', 'horizon'), '/');

        return url($path);
    }

    public static function failedJobUrl(string $uuid): ?string
    {
        if (! static::isInstalled() || $uuid === '') {
            return null;
        }

        if (! interface_exists(JobRepository::class)) {
            return null;
        }

        $failed = app(JobRepository::class)->findFailed($uuid);

        if ($failed === null) {
            return null;
        }

        $path = trim((string) config('horizon.path', 'horizon'), '/');

        return url("{$path}/failed/{$uuid}");
    }
}
