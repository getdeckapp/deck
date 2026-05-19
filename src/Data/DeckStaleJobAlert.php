<?php

namespace Deck\Deck\Data;

use Illuminate\Support\Carbon;

class DeckStaleJobAlert
{
    public function __construct(
        public readonly string $jobClass,
        public readonly int $maxAgeHours,
        public readonly ?Carbon $lastFinishedAt,
    ) {}
}
