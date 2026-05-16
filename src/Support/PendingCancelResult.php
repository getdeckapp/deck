<?php

namespace TorMorten\Deck\Support;

class PendingCancelResult
{
    public function __construct(
        public readonly bool $cancelFlagSet,
        public readonly bool $removedFromQueue,
        public readonly string $message,
    ) {}
}
