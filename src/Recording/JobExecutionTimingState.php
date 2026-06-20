<?php

namespace Deck\Deck\Recording;

use Illuminate\Support\Carbon;

/**
 * Cached lifecycle state for a single job execution attempt.
 *
 * Lets the recording producer answer "did this attempt already start / finish /
 * get blocked?" from the cache alone, without reading the executions table.
 */
readonly class JobExecutionTimingState
{
    public const string Running = 'running';

    public const string Blocked = 'blocked';

    public const string Terminal = 'terminal';

    public function __construct(
        public string $status,
        public ?Carbon $startedAt = null,
        public ?int $waitMs = null,
    ) {}

    public function isRunning(): bool
    {
        return $this->status === self::Running;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::Blocked;
    }

    public function isTerminal(): bool
    {
        return $this->status === self::Terminal;
    }
}
