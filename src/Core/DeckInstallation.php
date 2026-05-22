<?php

namespace Deck\Deck\Core;

class DeckInstallation
{
    public static function project(): string
    {
        return (string) config('deck.project', config('app.name', 'laravel'));
    }

    public static function environment(): string
    {
        return (string) config('deck.environment', config('app.env', 'production'));
    }
}
