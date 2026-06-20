<?php

namespace Deck\Deck\Recording;

use Deck\Deck\Core\Concerns\RunsSilently;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Caches per-attempt execution lifecycle state (start time, wait time, and
 * phase) in the cache so terminal lifecycle hooks never read the executions
 * table. This keeps the recording producer storage-agnostic: the database is
 * a write-only sink, never a source the producer reads back from.
 *
 * The running phase is held for the full progress TTL so long jobs survive;
 * terminal/blocked phases are kept briefly (just long enough to de-duplicate
 * the trailing JobAttempted safety net) and otherwise cleaned up eagerly.
 */
class JobExecutionTiming
{
    use RunsSilently;

    public static function cacheKey(string $uuid, int $attempt): string
    {
        return 'deck:timing:'.$uuid.':'.$attempt;
    }

    public static function remember(string $uuid, int $attempt, Carbon $startedAt, ?int $waitMs = null): void
    {
        self::put($uuid, $attempt, [
            'status' => JobExecutionTimingState::Running,
            'started_at' => $startedAt->toIso8601String(),
            'wait_ms' => $waitMs,
        ], self::runningTtlSeconds());
    }

    public static function markBlocked(string $uuid, int $attempt): void
    {
        self::put($uuid, $attempt, [
            'status' => JobExecutionTimingState::Blocked,
        ], self::terminalTtlSeconds());
    }

    public static function markTerminal(string $uuid, int $attempt): void
    {
        self::put($uuid, $attempt, [
            'status' => JobExecutionTimingState::Terminal,
        ], self::terminalTtlSeconds());
    }

    public static function peek(string $uuid, int $attempt): ?JobExecutionTimingState
    {
        $value = self::runSilently(
            fn (): mixed => self::cache()->get(self::cacheKey($uuid, $attempt)),
        );

        if (! is_array($value) || ! isset($value['status']) || ! is_string($value['status'])) {
            return null;
        }

        return new JobExecutionTimingState(
            status: $value['status'],
            startedAt: self::parseStartedAt($value['started_at'] ?? null),
            waitMs: isset($value['wait_ms']) && is_numeric($value['wait_ms']) ? (int) $value['wait_ms'] : null,
        );
    }

    public static function forget(string $uuid, int $attempt): void
    {
        self::runSilentlyVoid(
            fn (): mixed => self::cache()->forget(self::cacheKey($uuid, $attempt)),
        );
    }

    private static function parseStartedAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function put(string $uuid, int $attempt, array $payload, int $ttlSeconds): void
    {
        self::runSilentlyVoid(function () use ($uuid, $attempt, $payload, $ttlSeconds): void {
            self::cache()->put(
                self::cacheKey($uuid, $attempt),
                $payload,
                now()->addSeconds($ttlSeconds),
            );
        });
    }

    private static function runningTtlSeconds(): int
    {
        return max(1, (int) config('deck.progress_ttl_seconds', 86_400));
    }

    private static function terminalTtlSeconds(): int
    {
        return max(1, (int) config('deck.timing_terminal_ttl_seconds', 300));
    }

    private static function cache(): CacheRepository
    {
        $store = config('deck.progress_cache_store')
            ?? config('deck.cancel_cache_store')
            ?? config('cache.default');

        return app('cache')->store($store);
    }
}
