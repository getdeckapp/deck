<?php

namespace TorMorten\Deck\Support;

readonly class RetryExecutionResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}
}
