<?php

namespace Deck\Deck\Data;

class DeckUnprocessedQueueAlert
{
    public function __construct(
        public readonly UnprocessedQueue $queue,
    ) {}
}
