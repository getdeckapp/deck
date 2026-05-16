<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TorMorten\Deck\Deck;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\JobClassBlock;

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

    public function cancelAllRunning(): void
    {
        $count = app(Deck::class)->cancelAllRunningForClass($this->jobClass);

        if ($count > 0) {
            session()->flash(
                'status',
                "Cancellation requested for {$count} running ".str('execution')->plural($count).'. Only jobs using the Cancellable middleware will stop cooperatively.',
            );

            return;
        }

        session()->flash('status', 'No running executions to cancel for this class.');
    }

    public function blockClass(?string $duration = null): void
    {
        $until = match ($duration) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            default => null,
        };

        app(Deck::class)->blockClass($this->jobClass, $until);

        $message = $until !== null
            ? 'Job blocked until '.$until->diffForHumans().'. Running jobs are being cancelled; new dispatches are recorded as blocked and never queued.'
            : 'Job blocked until you unblock it. Running jobs are being cancelled; new dispatches are recorded as blocked and never queued.';

        session()->flash('status', $message);
    }

    public function unblockClass(): void
    {
        app(Deck::class)->unblockClass($this->jobClass);

        session()->flash('status', 'Job unblocked. New dispatches will be queued normally.');
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

        $runningCount = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $this->jobClass)
            ->where('status', JobExecutionStatus::Running)
            ->count();

        $isBlocked = JobClassBlock::isBlocked($this->jobClass);
        $blockedUntil = JobClassBlock::blockedUntil($this->jobClass);
        $isManualBlock = JobClassBlock::isManualBlock($this->jobClass);

        return view('deck::livewire.job-class-show', [
            'stat' => $stat,
            'executions' => $executions,
            'hasRunning' => $this->executionsHaveRunning($executions),
            'shouldPoll' => $this->shouldPollExecutions($executions),
            'runningCount' => $runningCount,
            'isBlocked' => $isBlocked,
            'blockedUntil' => $blockedUntil,
            'isManualBlock' => $isManualBlock,
            'jobClass' => $this->jobClass,
            'avgDurationMs' => $avgDuration ? (int) round($avgDuration) : null,
            'statuses' => JobExecutionStatus::cases(),
        ]);
    }
}
