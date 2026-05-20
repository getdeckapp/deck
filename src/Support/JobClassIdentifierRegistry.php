<?php

namespace Deck\Deck\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Job as QueueJobContract;

class JobClassIdentifierRegistry
{
    /**
     * @return list<string>
     */
    public static function forQueueJob(QueueJobContract $job): array
    {
        $identifiers = [];

        if (method_exists($job, 'resolveQueuedJobClass')) {
            $identifiers[] = $job->resolveQueuedJobClass();
        }

        if (method_exists($job, 'resolveName')) {
            $identifiers[] = $job->resolveName();
        }

        $expanded = [];

        foreach (array_values(array_unique(array_filter($identifiers))) as $identifier) {
            $expanded = array_merge($expanded, static::expand($identifier));
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return list<string>
     */
    public static function expand(string $identifier): array
    {
        $identifiers = [$identifier];

        foreach (static::linkedIdentifiers($identifier) as $linked) {
            $identifiers[] = $linked;
        }

        return array_values(array_unique($identifiers));
    }

    public static function link(string $first, string $second): void
    {
        if ($first === '' || $second === '' || $first === $second) {
            return;
        }

        static::cache()->forever(static::linkCacheKey($first), $second);
        static::cache()->forever(static::linkCacheKey($second), $first);
    }

    public static function rememberFromQueueJob(QueueJobContract $job): void
    {
        if (! method_exists($job, 'resolveQueuedJobClass') || ! method_exists($job, 'resolveName')) {
            return;
        }

        // Identifier linking is only needed for future dispatches — defer off the hot path.
        DeferJobLifecycleRecording::run(
            fn (): mixed => static::link($job->resolveName(), $job->resolveQueuedJobClass()),
        );
    }

    private static function linkedIdentifiers(string $identifier): array
    {
        $linked = static::cache()->get(static::linkCacheKey($identifier));

        return is_string($linked) && $linked !== '' ? [$linked] : [];
    }

    private static function linkCacheKey(string $identifier): string
    {
        return 'deck:block:link:'.hash('sha256', $identifier);
    }

    private static function cache(): CacheRepository
    {
        return JobClassBlock::cacheRepository();
    }
}
