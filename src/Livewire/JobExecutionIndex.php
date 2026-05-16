<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TorMorten\Deck\Deck;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\Concerns\FiltersExecutions;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\ExecutionTagCatalog;

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

    #[Url]
    public string $pendingUuid = '';

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

    public function cancelPending(): void
    {
        $uuid = trim($this->pendingUuid);

        if ($uuid === '') {
            session()->flash('status', 'Enter a job UUID to cancel a queued job.');

            return;
        }

        $result = app(Deck::class)->cancelPending($uuid);

        session()->flash('status', $result->message);
        $this->pendingUuid = '';
    }

    public function render()
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

        return view('deck::livewire.job-execution-index', [
            'executions' => $executions,
            'queues' => $queues,
            'connections' => $connections,
            'tags' => app(ExecutionTagCatalog::class)->tags(),
            'hasRunning' => $this->executionsHaveRunning($executions),
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
