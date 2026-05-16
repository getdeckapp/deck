<?php

namespace TorMorten\Deck\Support;

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
}
