<?php

namespace Deck\Deck\Data;

readonly class RuntimeRollup
{
    public function __construct(
        public int $sampleCount,
        public ?int $avgMs,
        public ?int $p50Ms,
        public ?int $p95Ms,
        public ?float $failureRate,
        public int $completedCount,
        public int $failedCount,
    ) {}

    public function hasSamples(): bool
    {
        return $this->sampleCount > 0;
    }
}
