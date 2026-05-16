<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

class JobClassBlock
{
    private const string ManualMarker = 'manual';

    public static function cacheKey(string $jobClass): string
    {
        return 'deck:block:'.hash('sha256', $jobClass);
    }

    public static function block(string $jobClass, ?Carbon $until = null): void
    {
        if ($until !== null) {
            static::cache()->put(
                static::cacheKey($jobClass),
                $until->toIso8601String(),
                $until,
            );

            return;
        }

        static::cache()->put(
            static::cacheKey($jobClass),
            self::ManualMarker,
            now()->addSeconds((int) config('deck.block_manual_ttl_seconds', 31_536_000)),
        );
    }

    public static function unblock(string $jobClass): void
    {
        static::cache()->forget(static::cacheKey($jobClass));
    }

    public static function isBlocked(string $jobClass): bool
    {
        $value = static::cache()->get(static::cacheKey($jobClass));

        if ($value === null) {
            return false;
        }

        if ($value === self::ManualMarker) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $until = Carbon::parse($value);

        if ($until->isPast()) {
            static::unblock($jobClass);

            return false;
        }

        return true;
    }

    public static function blockedUntil(string $jobClass): ?Carbon
    {
        $value = static::cache()->get(static::cacheKey($jobClass));

        if ($value === null || $value === self::ManualMarker) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $until = Carbon::parse($value);

        return $until->isFuture() ? $until : null;
    }

    public static function isManualBlock(string $jobClass): bool
    {
        return static::cache()->get(static::cacheKey($jobClass)) === self::ManualMarker;
    }

    public static function releaseDelaySeconds(): int
    {
        return max(1, (int) config('deck.block_release_seconds', 60));
    }

    private static function cache(): CacheRepository
    {
        $store = config('deck.block_cache_store') ?? config('deck.cancel_cache_store') ?? config('cache.default');

        return app('cache')->store($store);
    }
}
