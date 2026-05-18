<?php

namespace TorMorten\Deck\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void cancel(string $uuid)
 * @method static bool isCancelled(string $uuid)
 * @method static bool cancelExecution(string $uuid, ?int $attempt = null)
 * @method static \TorMorten\Deck\Support\PendingCancelResult|null requestCancelExecution(string $uuid, ?int $attempt = null)
 * @method static \TorMorten\Deck\Support\PendingCancelResult|null forceCancelExecution(string $uuid, ?int $attempt = null)
 * @method static \TorMorten\Deck\Support\PendingCancelResult cancelPending(string $uuid, ?string $connection = null, ?string $queue = null, bool $force = false)
 * @method static \TorMorten\Deck\Support\RetryExecutionResult retryExecution(string $uuid, ?int $attempt = null)
 * @method static int cancelAllRunningForClass(string $jobClass)
 * @method static void blockClass(string $jobClass, ?\Illuminate\Support\Carbon $until = null, bool $cancelRunning = true, ?string $reason = null)
 * @method static void unblockClass(string $jobClass)
 * @method static bool isClassBlocked(string $jobClass)
 * @method static ?\Illuminate\Support\Carbon classBlockedUntil(string $jobClass)
 * @method static \TorMorten\Deck\Support\JobClassBlockAudit|null classBlockAudit(string $jobClass)
 * @method static void updateProgress(string $uuid, int $percent, ?string $message = null)
 * @method static \TorMorten\Deck\Data\ClearQueueResult clearQueue(string $connection, string $queue)
 *
 * @see \TorMorten\Deck\Deck
 */
class Deck extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TorMorten\Deck\Deck::class;
    }
}
