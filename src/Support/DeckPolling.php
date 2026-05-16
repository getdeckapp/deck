<?php

namespace TorMorten\Deck\Support;

class DeckPolling
{
    public static function dashboardSeconds(int $runningCount = 0): int
    {
        if ($runningCount > 0) {
            return max(1, (int) config('deck.poll.dashboard_running_seconds', 2));
        }

        return max(1, (int) config('deck.poll.dashboard_seconds', 4));
    }

    public static function workersSeconds(): int
    {
        return max(1, (int) config('deck.poll.workers_seconds', 4));
    }

    public static function executionsSeconds(): int
    {
        return max(1, (int) config('deck.poll.executions_seconds', 2));
    }

    public static function activitySeconds(bool $hasRunning = false): int
    {
        if ($hasRunning) {
            return static::executionsSeconds();
        }

        return max(1, (int) config('deck.poll.activity_seconds', 3));
    }
}
