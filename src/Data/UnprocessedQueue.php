<?php

namespace Deck\Deck\Data;

class UnprocessedQueue
{
    public function __construct(
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $queueKey,
        public readonly int $pending,
        public readonly int $workerProcesses,
        public readonly string $horizonStatus,
        public readonly string $suggestion,
    ) {}
}
