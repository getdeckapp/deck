<?php

namespace Deck\Deck\Recording;

use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\Jobs\JobName;

/**
 * Resolves job class and display names across Laravel releases. Older queue
 * workers expose resolveName() but not resolveQueuedJobClass() on RedisJob.
 */
class QueuedJobResolver
{
    /**
     * User-facing job class for Deck (e.g. App\Mail\Campaign, not SendQueuedMailable).
     */
    public static function resolveClass(QueueJobContract $job): string
    {
        $handler = static::resolveHandlerClass($job);
        $display = static::resolveDisplayName($job);

        if ($display !== $handler) {
            return $display;
        }

        return $handler;
    }

    /**
     * Underlying queued command / handler class (SendQueuedMailable, CallQueuedHandler, etc.).
     */
    public static function resolveHandlerClass(QueueJobContract $job): string
    {
        $resolved = static::tryResolveQueuedJobClass($job);

        if ($resolved !== null) {
            return $resolved;
        }

        return static::resolveClassFromPayload(static::jobName($job), $job->payload());
    }

    public static function resolveDisplayName(QueueJobContract $job): string
    {
        $resolved = static::tryResolveDisplayName($job);

        if ($resolved !== null) {
            return $resolved;
        }

        return static::resolveDisplayNameFromPayload(static::jobName($job), $job->payload());
    }

    private static function tryResolveQueuedJobClass(QueueJobContract $job): ?string
    {
        try {
            if (! method_exists($job, 'resolveQueuedJobClass')) {
                return null;
            }

            $class = $job->resolveQueuedJobClass();

            return is_string($class) && $class !== '' ? $class : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function tryResolveDisplayName(QueueJobContract $job): ?string
    {
        try {
            if (! method_exists($job, 'resolveName')) {
                return null;
            }

            $name = $job->resolveName();

            return is_string($name) && $name !== '' ? $name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function resolveClassFromPayload(string $name, array $payload): string
    {
        if (class_exists(JobName::class) && method_exists(JobName::class, 'resolveClassName')) {
            return JobName::resolveClassName($name, $payload);
        }

        if (is_string($payload['data']['commandName'] ?? null)) {
            return $payload['data']['commandName'];
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function resolveDisplayNameFromPayload(string $name, array $payload): string
    {
        if (class_exists(JobName::class) && method_exists(JobName::class, 'resolve')) {
            return JobName::resolve($name, $payload);
        }

        if (! empty($payload['displayName'])) {
            return (string) $payload['displayName'];
        }

        return $name;
    }

    private static function jobName(QueueJobContract $job): string
    {
        return $job->getName();
    }
}
