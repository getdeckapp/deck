<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobExecution;

#[Layout('deck::layouts.app')]
class JobExecutionShow extends Component
{
    use InteractsWithExecutions;

    public JobExecution $execution;

    public function mount(JobExecution $execution): void
    {
        $this->execution = JobExecution::query()
            ->forInstallation()
            ->whereKey($execution->getKey())
            ->firstOrFail();
    }

    public function render()
    {
        return view('deck::livewire.job-execution-show', [
            'execution' => $this->execution,
        ]);
    }
}
