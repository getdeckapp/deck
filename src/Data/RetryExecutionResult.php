<?php

namespace Deck\Deck\Data;

readonly class RetryExecutionResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}
}
