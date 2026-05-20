<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Livewire\Concerns\FiltersExecutions;
use Deck\Deck\Livewire\Concerns\InteractsWithExecutions;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckPolling;
use Deck\Deck\Support\ExecutionTagCatalog;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('deck::layouts.app')]
class JobExecutionIndex extends Component
{
    use FiltersExecutions;
    use InteractsWithExecutions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $queue = '';

    #[Url]
    public string $connection = '';

    #[Url]
    public string $tag = '';

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

    public function updatedConnection(): void
    {
        $this->resetPage();
    }

    public function updatedTag(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function render(): View
    {
        $executions = $this->filteredExecutionsQuery()
            ->orderByDesc('started_at')
            ->paginate(50);

        $scope = JobExecution::query()->forInstallation();

        $queues = (clone $scope)
            ->select('queue')
            ->distinct()
            ->orderBy('queue')
            ->pluck('queue');

        $connections = (clone $scope)
            ->select('connection')
            ->distinct()
            ->orderBy('connection')
            ->pluck('connection');

        $hasRunning = JobExecution::hasRunningForInstallation();

        return view('deck::livewire.job-execution-index', [
            'executions' => $executions,
            'queues' => $queues,
            'connections' => $connections,
            'tags' => app(ExecutionTagCatalog::class)->tags(),
            'shouldPoll' => true,
            'pollSeconds' => DeckPolling::activitySeconds($hasRunning),
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
