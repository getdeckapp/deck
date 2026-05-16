<?php

namespace TorMorten\Deck;

use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\JobCancellation;

class Deck
{
    public function cancel(string $uuid): void
    {
        JobCancellation::cancel($uuid);
    }

    public function isCancelled(string $uuid): bool
    {
        return JobCancellation::isCancelled($uuid);
    }

    public function cancelExecution(int $executionId): bool
    {
        $execution = JobExecution::query()->find($executionId);

        if ($execution === null) {
            return false;
        }

        if ($execution->status->value !== 'running') {
            return false;
        }

        JobCancellation::cancel($execution->uuid);

        return true;
    }
}
