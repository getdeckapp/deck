<?php

namespace TorMorten\Deck\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void cancel(string $uuid)
 * @method static bool isCancelled(string $uuid)
 * @method static bool cancelExecution(string $uuid, ?int $attempt = null)
 * @method static \TorMorten\Deck\Support\RetryExecutionResult retryExecution(string $uuid, ?int $attempt = null)
 * @method static int cancelAllRunningForClass(string $jobClass)
 * @method static void blockClass(string $jobClass, ?\Illuminate\Support\Carbon $until = null, bool $cancelRunning = true)
 * @method static void unblockClass(string $jobClass)
 * @method static bool isClassBlocked(string $jobClass)
 * @method static ?\Illuminate\Support\Carbon classBlockedUntil(string $jobClass)
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
