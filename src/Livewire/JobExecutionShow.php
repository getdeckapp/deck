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

        return view('deck::livewire.job-execution-show', [
            'execution' => $this->execution,
        ]);
    }
}
