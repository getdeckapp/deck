<?php

namespace TorMorten\Deck\Support;

class DeckAssets
{
    public static function styles(): string
    {
        $url = e(static::url('deck.css'));

        return "<link rel=\"stylesheet\" href=\"{$url}\">";
    }

    public static function url(string $file): string
    {
        $file = basename($file);

        $publishedPath = public_path("vendor/deck/{$file}");

        if (is_file($publishedPath)) {
            return asset("vendor/deck/{$file}").'?v='.filemtime($publishedPath);
        }

        $packagePath = static::packageDistPath($file);

        if (is_file($packagePath)) {
            return route('deck.assets', ['file' => $file], absolute: false).'?v='.filemtime($packagePath);
        }

        throw new \RuntimeException(
            "Deck asset [{$file}] is missing. Publish assets with `php artisan vendor:publish --tag=deck-assets` or build the package with `npm run build`."
        );
    }

    public static function packageDistPath(string $file): string
    {
        return dirname(__DIR__, 2).'/resources/dist/'.basename($file);
    }
}
