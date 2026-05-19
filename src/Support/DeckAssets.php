<?php

namespace Deck\Deck\Support;

class DeckAssets
{
    public static function styles(): string
    {
        $url = e(static::url('deck.css'));

        return "<link rel=\"stylesheet\" href=\"{$url}\">";
    }

    public static function url(string $file): string
    {
        $path = static::resolveAssetPath($file);

        if (static::isPublishedPath($path)) {
            return asset('vendor/deck/'.basename($file)).'?v='.filemtime($path);
        }

        return route('deck.assets', ['file' => basename($file)], absolute: false).'?v='.filemtime($path);
    }

    public static function resolveAssetPath(string $file): string
    {
        $file = basename($file);

        $publishedPath = public_path("vendor/deck/{$file}");
        $packagePath = static::packageDistPath($file);

        $publishedExists = is_file($publishedPath);
        $packageExists = is_file($packagePath);

        if ($publishedExists && $packageExists) {
            return filemtime($packagePath) > filemtime($publishedPath)
                ? $packagePath
                : $publishedPath;
        }

        if ($publishedExists) {
            return $publishedPath;
        }

        if ($packageExists) {
            return $packagePath;
        }

        throw new \RuntimeException(
            "Deck asset [{$file}] is missing. Run `npm run build` in the Deck package, then `php artisan deck:install --force` (or `vendor:publish --tag=deck-assets --force`)."
        );
    }

    public static function packageDistPath(string $file): string
    {
        return dirname(__DIR__, 2).'/resources/dist/'.basename($file);
    }

    protected static function isPublishedPath(string $path): bool
    {
        $publishedRoot = realpath(public_path('vendor/deck'));

        if ($publishedRoot === false) {
            return false;
        }

        $resolved = realpath($path);

        if ($resolved === false) {
            return false;
        }

        return str_starts_with($resolved, $publishedRoot);
    }
}
