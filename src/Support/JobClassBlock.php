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

    public static function auditCacheKey(string $jobClass): string
    {
        return 'deck:block:audit:'.hash('sha256', $jobClass);
    }

    public static function block(string $jobClass, ?Carbon $until = null, ?string $reason = null): void
    {
        foreach (JobClassIdentifierRegistry::expand($jobClass) as $identifier) {
            static::putBlock($identifier, $until, $reason);
        }
    }

    public static function unblock(string $jobClass): void
    {
        foreach (JobClassIdentifierRegistry::expand($jobClass) as $identifier) {
            static::cache()->forget(static::cacheKey($identifier));
            static::cache()->forget(static::auditCacheKey($identifier));
        }
    }

    public static function audit(string $jobClass): ?JobClassBlockAudit
    {
        if (! static::isBlocked($jobClass)) {
            return null;
        }

        $payload = static::cache()->get(static::auditCacheKey($jobClass));

        if (! is_array($payload)) {
            return null;
        }

        return JobClassBlockAudit::fromCache($payload);
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

    private static function putBlock(string $jobClass, ?Carbon $until, ?string $reason): void
    {
        $expiresAt = $until ?? now()->addSeconds((int) config('deck.block_manual_ttl_seconds', 31_536_000));

        if ($until !== null) {
            static::cache()->put(
                static::cacheKey($jobClass),
                $until->toIso8601String(),
                $until,
            );
        } else {
            static::cache()->put(
                static::cacheKey($jobClass),
                self::ManualMarker,
                $expiresAt,
            );
        }

        static::putAudit($jobClass, $reason, $expiresAt);
    }

    private static function putAudit(string $jobClass, ?string $reason, Carbon $expiresAt): void
    {
        $normalizedReason = static::normalizeReason($reason);

        static::cache()->put(
            static::auditCacheKey($jobClass),
            [
                'reason' => $normalizedReason,
                'blocked_at' => now()->toIso8601String(),
                'blocked_by' => static::resolveBlockedBy(),
            ],
            $expiresAt,
        );
    }

    private static function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        if ($trimmed === '') {
            return null;
        }

        $maxLength = max(1, (int) config('deck.block_reason_max_length', 500));

        return mb_substr($trimmed, 0, $maxLength);
    }

    private static function resolveBlockedBy(): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        if (method_exists($user, 'getAttribute')) {
            $email = $user->getAttribute('email');

            if (is_string($email) && $email !== '') {
                return $email;
            }

            $name = $user->getAttribute('name');

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return null;
    }

    private static function cache(): CacheRepository
    {
        return static::cacheRepository();
    }
}
