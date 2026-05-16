<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobExecution;

#[Layout('deck::layouts.app')]
class JobExecutionIndex extends Component
{
    use InteractsWithExecutions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $queue = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedQueue(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function render()
    {
        $query = JobExecution::query()
            ->forInstallation()
            ->orderByDesc('started_at');

        if ($this->search !== '') {
            $query->where(function ($query) {
                $query
                    ->where('job_class', 'like', '%'.$this->search.'%')
                    ->orWhere('uuid', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->status !== '' && JobExecutionStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        if ($this->queue !== '') {
            $query->where('queue', $this->queue);
        }

        $executions = $query->paginate(50);

        $queues = JobExecution::query()
            ->forInstallation()
            ->select('queue')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue');

        return view('deck::livewire.job-execution-index', [
            'executions' => $executions,
            'queues' => $queues,
            'hasRunning' => $this->executionsHaveRunning($executions),
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
