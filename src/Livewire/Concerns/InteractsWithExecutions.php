<?php

namespace Deck\Deck\Livewire\Concerns;

use Deck\Deck\Deck;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckPolling;

trait InteractsWithExecutions
{
    use InteractsWithActionConfirmation;

    public function cancelExecution(string $uuid, ?int $attempt = null): void
    {
        $result = app(Deck::class)->requestCancelExecution($uuid, $attempt);

        if ($result === null) {
            session()->flash('status', 'This execution cannot be cancelled (not running).');

            return;
        }

        session()->flash('status', $result->message);
    }

    public function forceCancelExecution(string $uuid, ?int $attempt = null): void
    {
        $result = app(Deck::class)->forceCancelExecution($uuid, $attempt);

        if ($result === null) {
            session()->flash('status', 'This execution cannot be force cancelled (not running).');

            return;
        }

        session()->flash('status', $result->message);
    }

    public function retryExecution(string $uuid, ?int $attempt = null): void
    {
        $result = app(Deck::class)->retryExecution($uuid, $attempt);

        session()->flash('status', $result->message);
    }

    public function requestCancelExecutionConfirmation(
        string $uuid,
        ?int $attempt = null,
        bool $cancellationPending = false,
    ): void {
        $params = [$uuid, $attempt];
        $choices = [];

        if (! $cancellationPending) {
            $choices[] = [
                'method' => 'cancelExecution',
                'arguments' => $params,
                'label' => 'Request cancel',
                'progressLabel' => 'Requesting…',
                'tone' => 'warning',
                'description' => 'Cooperative cancel: sets the cancel flag and removes ready or delayed Redis payloads. The worker stops at the next Cancellable middleware check.',
            ];
        }

        $choices[] = [
            'method' => 'forceCancelExecution',
            'arguments' => $params,
            'label' => 'Force cancel',
            'progressLabel' => 'Force cancelling…',
            'tone' => 'danger',
            'description' => $cancellationPending
                ? 'Removes reserved Redis payloads (best effort), keeps the cancel flag, and marks this execution cancelled immediately. The PHP worker may still finish its current step.'
                : 'Removes reserved Redis payloads (best effort), keeps the cancel flag, and marks this execution cancelled immediately. Use when cooperative cancel is not stopping the job.',
        ];

        $this->pendingConfirmation = [
            'title' => $cancellationPending ? 'Escalate cancellation' : 'Cancel running job',
            'message' => $cancellationPending
                ? 'A cooperative cancel is already in progress. You can wait for the worker to stop, or force cancel for immediate effect.'
                : 'Choose how to stop this execution.',
            'choices' => $choices,
        ];
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

    protected function shouldPollExecutions(iterable $executions): bool
    {
        if ($this->executionsHaveRunning($executions)) {
            return true;
        }

        return JobExecution::hasPendingCancellationsForInstallation();
    }

    protected function executionPollSeconds(): int
    {
        return DeckPolling::executionsSeconds();
    }
}
