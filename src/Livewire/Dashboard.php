<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckHorizon;

#[Layout('deck::layouts.app')]
class Dashboard extends Component
{
    use InteractsWithExecutions;

    public function render()
    {
        $scope = JobClassStat::query()->forInstallation();

        $running = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Running)
            ->orderBy('started_at')
            ->limit(10)
            ->get();

        $recentFailures = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Failed)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $recentActivity = JobExecution::query()
            ->forInstallation()
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
            'running' => $running,
            'recentFailures' => $recentFailures,
            'recentActivity' => $recentActivity,
            'hasRunning' => $running->isNotEmpty(),
            'horizonUrl' => DeckHorizon::dashboardUrl(),
        ]);
    }
}
