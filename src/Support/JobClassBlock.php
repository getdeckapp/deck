<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
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
        foreach (JobClassIdentifierRegistry::expand($jobClass) as $identifier) {
            static::putBlock($identifier, $until);
        }
    }

    public static function unblock(string $jobClass): void
    {
        foreach (JobClassIdentifierRegistry::expand($jobClass) as $identifier) {
            static::cache()->forget(static::cacheKey($identifier));
        }
    }

    public static function isBlockedForCommand(object $command): bool
    {
        foreach (JobClassIdentifierRegistry::expand($command::class) as $identifier) {
            if (static::isBlocked($identifier)) {
                return true;
            }
        }

        return false;
    }

    public static function isBlockedForJob(QueueJobContract $job): bool
    {
        foreach (JobClassIdentifierRegistry::forQueueJob($job) as $identifier) {
            if (static::isBlocked($identifier)) {
                return true;
            }
        }

        return false;
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

    public static function cacheRepository(): CacheRepository
    {
        $store = config('deck.block_cache_store') ?? config('deck.cancel_cache_store');

        if ($store === null && config('queue.default') === 'redis') {
            $store = 'redis';
        }

        return app('cache')->store($store ?? config('cache.default'));
    }

    private static function putBlock(string $jobClass, ?Carbon $until): void
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

    private static function cache(): CacheRepository
    {
        return static::cacheRepository();
    }
}
