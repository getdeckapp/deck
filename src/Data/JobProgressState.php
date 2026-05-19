<?php

namespace Deck\Deck\Data;

readonly class JobProgressState
{
    public function __construct(
        public int $percent,
        public ?string $message,
        public string $updatedAt,
    ) {}
}
