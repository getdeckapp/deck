<?php

namespace TorMorten\Deck\Data;

readonly class ClearQueueResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?string $connection = null,
        public ?string $queue = null,
    ) {}
}
