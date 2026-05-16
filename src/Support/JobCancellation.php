<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use TorMorten\Deck\Exceptions\JobCancelledException;

class JobCancellation
{
    public static function cacheKey(string $uuid): string
    {
        return 'deck:cancel:'.$uuid;
    }

    public static function cancel(string $uuid): void
    {
        static::cache()->put(
            static::cacheKey($uuid),
            true,
            now()->addSeconds((int) config('deck.cancel_ttl_seconds', 86_400)),
        );
    }

    public static function isCancelled(string $uuid): bool
    {
        return static::cache()->get(static::cacheKey($uuid)) === true;
    }

    public static function clear(string $uuid): void
    {
        static::cache()->forget(static::cacheKey($uuid));
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
        if (method_exists($job, 'uuid')) {
            return $job->uuid();
        }

        if (! method_exists($job, 'payload')) {
            return null;
        }

        $payload = $job->payload();

        return $payload['uuid'] ?? null;
    }

    private static function cache(): CacheRepository
    {
        $store = config('deck.cancel_cache_store') ?? config('cache.default');

        return app('cache')->store($store);
    }
}
