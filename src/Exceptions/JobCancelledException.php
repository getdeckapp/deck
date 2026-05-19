<?php

namespace Deck\Deck\Exceptions;

use RuntimeException;

class JobCancelledException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct("Job [{$uuid}] was cancelled via Deck.");
    }
}
