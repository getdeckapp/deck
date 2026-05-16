<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;

#[Layout('deck::layouts.app')]
class JobClassShow extends Component
{
    use InteractsWithExecutions;
    use WithPagination;

    public string $jobClass;

    #[Url]
    public string $status = '';

    public function mount(string $jobClass): void
    {
        $this->jobClass = $jobClass;
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
        $stat = JobClassStat::query()
            ->forInstallation()
            ->where('job_class', $this->jobClass)
            ->first();

        $query = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $this->jobClass)
            ->orderByDesc('started_at');

        if ($this->status !== '' && JobExecutionStatus::tryFrom($this->status)) {
            $query->where('status', $this->status);
        }

        $executions = $query->paginate(50);

        $avgDuration = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $this->jobClass)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        return view('deck::livewire.job-class-show', [
            'stat' => $stat,
            'executions' => $executions,
            'hasRunning' => $this->executionsHaveRunning($executions),
            'jobClass' => $this->jobClass,
            'avgDurationMs' => $avgDuration ? (int) round($avgDuration) : null,
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
