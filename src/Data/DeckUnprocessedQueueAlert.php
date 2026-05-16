<?php

namespace TorMorten\Deck\Data;

class DeckUnprocessedQueueAlert
{
    public function __construct(
        public readonly UnprocessedQueue $queue,
    ) {}
}
