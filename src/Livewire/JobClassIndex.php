<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;

#[Layout('deck::layouts.app')]
class JobClassIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
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
        $query = JobClassStat::query()
            ->forInstallation()
            ->orderByDesc('last_finished_at');

        if ($this->search !== '') {
            $query->where('job_class', 'like', '%'.$this->search.'%');
        }

        if ($this->status !== '' && JobExecutionStatus::tryFrom($this->status)) {
            $query->where('last_status', $this->status);
        }

        $scope = JobClassStat::query()->forInstallation();

        return view('deck::livewire.job-class-index', [
            'stats' => $query->paginate(25),
            'summary' => [
                'classes' => (clone $scope)->count(),
                'running' => (clone $scope)->where('last_status', JobExecutionStatus::Running)->count(),
                'failed' => (clone $scope)->where('last_status', JobExecutionStatus::Failed)->count(),
                'successes' => (int) (clone $scope)->sum('success_count'),
            ],
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
