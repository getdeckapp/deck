<?php

namespace Deck\Deck\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void cancel(string $uuid)
 * @method static bool isCancelled(string $uuid)
 * @method static bool cancelExecution(string $uuid, ?int $attempt = null)
 * @method static \Deck\Deck\Data\PendingCancelResult|null requestCancelExecution(string $uuid, ?int $attempt = null)
 * @method static \Deck\Deck\Data\PendingCancelResult|null forceCancelExecution(string $uuid, ?int $attempt = null)
 * @method static \Deck\Deck\Data\PendingCancelResult cancelPending(string $uuid, ?string $connection = null, ?string $queue = null, bool $force = false)
 * @method static \Deck\Deck\Data\RetryExecutionResult retryExecution(string $uuid, ?int $attempt = null)
 * @method static int cancelAllRunningForClass(string $jobClass)
 * @method static void blockClass(string $jobClass, ?\Illuminate\Support\Carbon $until = null, bool $cancelRunning = true, ?string $reason = null)
 * @method static void unblockClass(string $jobClass)
 * @method static bool isClassBlocked(string $jobClass)
 * @method static ?\Illuminate\Support\Carbon classBlockedUntil(string $jobClass)
 * @method static \Deck\Deck\Data\JobClassBlockAudit|null classBlockAudit(string $jobClass)
 * @method static void updateProgress(string $uuid, int $percent, ?string $message = null)
 * @method static \Deck\Deck\Data\ClearQueueResult clearQueue(string $connection, string $queue)
 *
 * @see \Deck\Deck\Deck
 */
class Deck extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Deck\Deck\Deck::class;
    }
}
