<?php

namespace Deck\Deck\Recording;

use Deck\Deck\Core\Concerns\RunsSilently;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Caches execution start times in Redis so terminal lifecycle hooks avoid an
 * extra database read for started_at (status-only lookups stay minimal).
 */
class JobExecutionTiming
{
    use RunsSilently;

    public static function cacheKey(string $uuid, int $attempt): string
    {
        return 'deck:timing:'.$uuid.':'.$attempt;
    }

    public static function remember(string $uuid, int $attempt, Carbon $startedAt): void
    {
        static::runSilentlyVoid(function () use ($uuid, $attempt, $startedAt): void {
            static::cache()->put(
                static::cacheKey($uuid, $attempt),
                $startedAt->toIso8601String(),
                now()->addSeconds((int) config('deck.progress_ttl_seconds', 86_400)),
            );
        });
    }

    public static function resolve(string $uuid, int $attempt): ?Carbon
    {
        $value = static::runSilently(
            fn (): mixed => static::cache()->pull(static::cacheKey($uuid, $attempt)),
        );

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function forget(string $uuid, int $attempt): void
    {
        static::runSilentlyVoid(
            fn (): mixed => static::cache()->forget(static::cacheKey($uuid, $attempt)),
        );
    }

    private static function cache(): CacheRepository
    {
        $store = config('deck.progress_cache_store')
            ?? config('deck.cancel_cache_store')
            ?? config('cache.default');

        return app('cache')->store($store);
    }
}
