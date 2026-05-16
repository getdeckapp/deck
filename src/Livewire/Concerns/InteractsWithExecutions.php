<?php

namespace TorMorten\Deck\Livewire\Concerns;

use TorMorten\Deck\Deck;
use TorMorten\Deck\Models\JobExecution;

trait InteractsWithExecutions
{
    public function cancelExecution(int $executionId): void
    {
        $cancelled = app(Deck::class)->cancelExecution($executionId);

        if ($cancelled) {
            session()->flash('status', 'Cancellation requested. The worker will stop at the next check.');

            return;
        }

        session()->flash('status', 'This execution cannot be cancelled.');
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
