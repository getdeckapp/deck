<?php

namespace TorMorten\Deck\Livewire\Concerns;

trait InteractsWithActionConfirmation
{
    /**
     * @var array{
     *     method?: string,
     *     arguments?: array<int, mixed>,
     *     title: string,
     *     message: string,
     *     confirmLabel?: string,
     *     progressLabel?: string,
     *     tone?: string,
     *     choices?: list<array{
     *         method: string,
     *         arguments: array<int, mixed>,
     *         label: string,
     *         progressLabel: string,
     *         tone: string,
     *         description: string,
     *     }>,
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

    public function executeConfirmedAction(?string $method = null): void
    {
        if ($this->pendingConfirmation === null) {
            return;
        }

        $pending = $this->pendingConfirmation;

        if (isset($pending['choices'])) {
            if ($method === null) {
                return;
            }

            $choice = collect($pending['choices'])->firstWhere('method', $method);

            if ($choice === null || ! method_exists($this, $method)) {
                $this->pendingConfirmation = null;

                return;
            }

            $this->{$method}(...$choice['arguments']);
            $this->pendingConfirmation = null;

            return;
        }

        $method ??= $pending['method'] ?? null;

        if ($method === null || ! method_exists($this, $method)) {
            $this->pendingConfirmation = null;

            return;
        }

        $this->{$method}(...($pending['arguments'] ?? []));
        $this->pendingConfirmation = null;
    }
}
