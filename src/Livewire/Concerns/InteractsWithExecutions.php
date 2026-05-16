<?php

namespace TorMorten\Deck\Livewire\Concerns;

use TorMorten\Deck\Deck;
use TorMorten\Deck\Models\JobExecution;

trait InteractsWithExecutions
{
    use InteractsWithActionConfirmation;

    public function cancelExecution(string $uuid, ?int $attempt = null): void
    {
        $cancelled = app(Deck::class)->cancelExecution($uuid, $attempt);

        if ($cancelled) {
            session()->flash('status', 'Cancellation requested. The worker will stop at the next cooperative check, and a best-effort Redis removal was attempted.');

            return;
        }

        session()->flash('status', 'This execution cannot be cancelled (not running).');
    }

    public function retryExecution(string $uuid, ?int $attempt = null): void
    {
        $result = app(Deck::class)->retryExecution($uuid, $attempt);

        session()->flash('status', $result->message);
    }

    protected function executionsHaveRunning(iterable $executions): bool
    {
        foreach ($executions as $execution) {
            if ($execution instanceof JobExecution && $execution->status->value === 'running') {
                return true;
            }
        }

        return false;
    }
}
