<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\Concerns\FiltersExecutions;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckHorizon;
use TorMorten\Deck\Support\ExecutionMetrics;
use TorMorten\Deck\Support\ExecutionTagCatalog;
use TorMorten\Deck\Support\HorizonSnapshot;
use TorMorten\Deck\Support\QueueBusyness;

#[Layout('deck::layouts.app')]
class Dashboard extends Component
{
    use FiltersExecutions;
    use InteractsWithExecutions;

    #[Url]
    public string $tag = '';

    public function updatedTag(): void
    {
        //
    }

    public function render()
    {
        $scope = JobClassStat::query()->forInstallation();
        $metrics = ExecutionMetrics::make();
        $horizon = app(HorizonSnapshot::class);

        $executionQuery = $this->filteredExecutionsQuery();

        $running = (clone $executionQuery)
            ->where('status', JobExecutionStatus::Running)
            ->orderBy('started_at')
            ->limit(10)
            ->get();

        $recentFailures = (clone $executionQuery)
            ->where('status', JobExecutionStatus::Failed)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $recentActivity = (clone $executionQuery)
            ->orderByDesc('started_at')
            ->limit(8)
            ->get();

        return view('deck::livewire.dashboard', [
            'summary' => [
                'classes' => (clone $scope)->count(),
                'running' => (clone $scope)->where('last_status', JobExecutionStatus::Running)->count(),
                'failed' => (clone $scope)->where('last_status', JobExecutionStatus::Failed)->count(),
                'successes' => (int) (clone $scope)->sum('success_count'),
                'executions' => JobExecution::query()->forInstallation()->count(),
            ],
            'jobVolumeChart' => $metrics->hourlyJobVolume()->all(),
            'durationChart' => $metrics->hourlyAverageDuration()->all(),
            'queueBusyness' => app(QueueBusyness::class)->assess(),
            'horizonAvailable' => $horizon->isAvailable(),
            'running' => $running,
            'recentFailures' => $recentFailures,
            'recentActivity' => $recentActivity,
            'hasRunning' => $running->isNotEmpty(),
            'shouldPoll' => $running->isNotEmpty() || $horizon->isAvailable(),
            'horizonUrl' => DeckHorizon::dashboardUrl(),
            'tags' => app(ExecutionTagCatalog::class)->tags(),
        ]);
    }
}
