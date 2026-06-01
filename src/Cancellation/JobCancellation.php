<?php

namespace Deck\Deck\Cancellation;

use Deck\Deck\Core\Concerns\RunsSilently;
use Deck\Deck\Exceptions\JobCancelledException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Support\Facades\Redis;

class JobCancellation
{
    use RunsSilently;

    public static function cacheKey(string $uuid): string
    {
        return 'deck:cancel:'.$uuid;
    }

    public static function cancel(string $uuid): void
    {
        static::runSilentlyVoid(function () use ($uuid): void {
            static::cache()->put(
                static::cacheKey($uuid),
                true,
                now()->addSeconds((int) config('deck.cancel_ttl_seconds', 86_400)),
            );
        });
    }

    /**
     * Single fast existence check — uses native Redis EXISTS when available,
     * never touches the database.
     */
    public static function isCancelled(string $uuid): bool
    {
        return static::runSilently(
            fn (): bool => static::cache()->has(static::cacheKey($uuid)),
            false,
        );
    }

    /**
     * @param  list<string>  $uuids
     */
    public static function anyCancelled(array $uuids): bool
    {
        if ($uuids === []) {
            return false;
        }

        return static::runSilently(
            fn (): bool => static::anyCancelledUnchecked($uuids),
            false,
        );
    }

    public static function clear(string $uuid): void
    {
        static::runSilentlyVoid(
            fn (): mixed => static::cache()->forget(static::cacheKey($uuid)),
        );
    }

    /**
     * Returns whether the job was cancelled and clears the flag in one step,
     * avoiding a separate exists + delete round trip on the hot path.
     */
    public static function consumeIfCancelled(string $uuid): bool
    {
        return static::runSilently(
            fn (): bool => static::consumeIfCancelledUnchecked($uuid),
            false,
        );
    }

    public static function throwIfCancelled(QueueJobContract $job): void
    {
        $uuid = static::uuidFromJob($job);

        if ($uuid !== null && static::isCancelled($uuid)) {
            throw new JobCancelledException($uuid);
        }
    }

    public static function uuidFromJob(QueueJobContract $job): ?string
    {
        $payload = $job->payload();
        $uuid = $payload['uuid'] ?? null;

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    /**
     * @param  list<string>  $uuids
     */
    private static function anyCancelledUnchecked(array $uuids): bool
    {
        $keys = array_map(static::cacheKey(...), $uuids);

        if ($redis = static::redisConnection()) {
            $prefixedKeys = array_map(static::prefixedCacheKey(...), $keys);

            // One EXISTS call for every running UUID instead of N separate reads.
            return (int) $redis->command('exists', $prefixedKeys) > 0;
        }

        $values = static::cache()->many($keys);

        foreach ($values as $value) {
            if ($value === true) {
                return true;
            }
        }

        return false;
    }

    private static function consumeIfCancelledUnchecked(string $uuid): bool
    {
        $key = static::cacheKey($uuid);

        if ($redis = static::redisConnection()) {
            // One DEL replaces separate exists + forget on the cooperative-cancel path.
            return (int) $redis->command('del', [static::prefixedCacheKey($key)]) > 0;
        }

        return static::cache()->pull($key) === true;
    }

    private static function prefixedCacheKey(string $key): string
    {
        $prefix = (string) config('cache.prefix', '');

        return $prefix !== '' ? $prefix.$key : $key;
    }

    private static function redisConnection(): ?RedisConnection
    {
        $store = config('deck.cancel_cache_store') ?? config('cache.default');

        if (config('cache.stores.'.$store.'.driver') !== 'redis') {
            return null;
        }

        $connection = config('cache.stores.'.$store.'.connection')
            ?? config('database.redis.default', 'default');

        try {
            return Redis::connection($connection);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function cache(): CacheRepository
    {
        $store = config('deck.cancel_cache_store') ?? config('cache.default');

        return app('cache')->store($store);
    }
}
