<?php

namespace Deck\Deck\Support;

class PendingCancelResult
{
    public function __construct(
        public readonly bool $cancelFlagSet,
        public readonly bool $removedFromQueue,
        public readonly bool $removedFromReserved,
        public readonly string $message,
    ) {}
}
