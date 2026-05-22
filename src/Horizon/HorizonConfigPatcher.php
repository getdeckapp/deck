<?php

namespace Deck\Deck\Horizon;

use Illuminate\Support\Facades\File;

class HorizonConfigPatcher
{
    public static function patch(): bool
    {
        $path = config_path('horizon.php');

        if (! File::exists($path)) {
            return false;
        }

        $middleware = 'Deck\\Deck\\Http\\Middleware\\PromptHorizonOrDeck::class';
        $contents = File::get($path);

        if (str_contains($contents, $middleware)) {
            return false;
        }

        $pattern = "/'middleware'\\s*=>\\s*\\[([^\\]]*)\\]/m";

        if (! preg_match($pattern, $contents, $matches)) {
            return false;
        }

        $replacement = "'middleware' => [{$matches[1]},\n        {$middleware},\n    ]";
        $updated = preg_replace($pattern, $replacement, $contents, 1);

        if ($updated === null) {
            return false;
        }

        File::put($path, $updated);

        return true;
    }
}
