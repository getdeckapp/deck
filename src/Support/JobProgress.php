<?php

namespace TorMorten\Deck\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use TorMorten\Deck\Data\JobProgressState;

class JobProgress
{
    public static function cacheKey(string $uuid): string
    {
        return 'deck:progress:'.$uuid;
    }

    public static function update(string $uuid, int $percent, ?string $message = null): void
    {
        $percent = max(0, min(100, $percent));

        static::cache()->put(
            static::cacheKey($uuid),
            [
                'percent' => $percent,
                'message' => $message !== null && $message !== '' ? mb_substr($message, 0, 500) : null,
                'updated_at' => now()->toIso8601String(),
            ],
            now()->addSeconds((int) config('deck.progress_ttl_seconds', 86_400)),
        );
    }

    public static function get(string $uuid): ?JobProgressState
    {
        $payload = static::cache()->get(static::cacheKey($uuid));

        if (! is_array($payload)) {
            return null;
        }

        return new JobProgressState(
            percent: max(0, min(100, (int) ($payload['percent'] ?? 0))),
            message: isset($payload['message']) && is_string($payload['message']) ? $payload['message'] : null,
            updatedAt: (string) ($payload['updated_at'] ?? now()->toIso8601String()),
        );
    }

    public static function clear(string $uuid): void
    {
        static::cache()->forget(static::cacheKey($uuid));
    }

    private static function cache(): CacheRepository
    {
        $store = config('deck.progress_cache_store')
            ?? config('deck.cancel_cache_store')
            ?? config('cache.default');

        return app('cache')->store($store);
    }
}
