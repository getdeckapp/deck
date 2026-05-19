<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Livewire\Concerns\InteractsWithExecutions;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckPolling;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('deck::layouts.app')]
class JobExecutionShow extends Component
{
    use InteractsWithExecutions;

    public JobExecution $execution;

    public function mount(string $uuid, int $attempt): void
    {
        $this->execution = JobExecution::query()
            ->forInstallation()
            ->where('uuid', $uuid)
            ->where('attempt', $attempt)
            ->firstOrFail();
    }

    public function render()
    {
        $this->execution->refresh();

        $shouldPoll = $this->execution->status->value === 'running'
            || $this->execution->isCancellationPending()
            || $this->execution->progress() !== null;

        return view('deck::livewire.job-execution-show', [
            'execution' => $this->execution,
            'shouldPoll' => $shouldPoll,
            'pollSeconds' => DeckPolling::executionsSeconds(),
        ]);
    }
}
