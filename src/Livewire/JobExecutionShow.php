<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Livewire\Concerns\InteractsWithExecutions;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Presentation\DeckPolling;
use Deck\Deck\Presentation\ExecutionObservability;
use Illuminate\View\View;
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

    public function render(): View
    {
        $this->execution->refresh();

        $shouldPoll = $this->execution->status->value === 'running'
            || $this->execution->isCancellationPending()
            || $this->execution->progress() !== null;

        $groupExecutions = $this->execution->dispatch_group_id
            ? ExecutionObservability::relatedGroupQuery($this->execution)->get()
            : collect();

        return view('deck::livewire.job-execution-show', [
            'execution' => $this->execution,
            'groupExecutions' => $groupExecutions,
            'parentExecution' => ExecutionObservability::parentExecution($this->execution),
            'childExecutions' => ExecutionObservability::childExecutionsQuery($this->execution)->get(),
            'shouldPoll' => $shouldPoll,
            'pollSeconds' => DeckPolling::executionsSeconds(),
        ]);
    }
}
