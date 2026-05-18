<?php

namespace TorMorten\Deck\Data;

readonly class DeckFailureRateAlert
{
    public function __construct(
        public string $jobClass,
        public float $failureRate,
        public float $maxFailureRate,
        public int $windowHours,
        public int $sampleCount,
        public int $failedCount,
    ) {}
}
