<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobClassStat;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    public function render(): View
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

        return view('deck::livewire.job-class-index', [
            'stats' => $query->paginate(25),
            'summary' => [
                'classes' => JobClassStat::query()->forInstallation()->count(),
                'running' => JobClassStat::query()->forInstallation()->where('last_status', JobExecutionStatus::Running)->count(),
                'failed' => JobClassStat::query()->forInstallation()->where('last_status', JobExecutionStatus::Failed)->count(),
                'successes' => (int) JobClassStat::query()->forInstallation()->sum('success_count'),
            ],
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
