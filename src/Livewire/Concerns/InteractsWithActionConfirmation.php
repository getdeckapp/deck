<?php

namespace TorMorten\Deck\Livewire\Concerns;

trait InteractsWithActionConfirmation
{
    /**
     * @var array{
     *     method: string,
     *     arguments: array<int, mixed>,
     *     title: string,
     *     message: string,
     *     confirmLabel: string,
     *     progressLabel: string,
     *     tone: string,
     * }|null
     */
    public ?array $pendingConfirmation = null;

    public function requestConfirmation(
        string $method,
        array $arguments,
        string $title,
        string $message,
        string $confirmLabel = 'Confirm',
        string $progressLabel = 'Working…',
        string $tone = 'primary',
    ): void {
        $this->pendingConfirmation = [
            'method' => $method,
            'arguments' => $arguments,
            'title' => $title,
            'message' => $message,
            'confirmLabel' => $confirmLabel,
            'progressLabel' => $progressLabel,
            'tone' => $tone,
        ];
    }

    public function cancelConfirmation(): void
    {
        $this->pendingConfirmation = null;
    }

    public function executeConfirmedAction(): void
    {
        if ($this->pendingConfirmation === null) {
            return;
        }

        $pending = $this->pendingConfirmation;
        $method = $pending['method'];

        if (! method_exists($this, $method)) {
            $this->pendingConfirmation = null;

            return;
        }

        $this->{$method}(...$pending['arguments']);
        $this->pendingConfirmation = null;
    }
}
