<?php

namespace TorMorten\Deck\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Enums\QueueBusynessLevel;
use TorMorten\Deck\Livewire\Concerns\InteractsWithExecutions;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;
use TorMorten\Deck\Support\DeckHorizon;
use TorMorten\Deck\Support\ExecutionMetrics;
use TorMorten\Deck\Support\HorizonSnapshot;
use TorMorten\Deck\Support\QueueBusyness;
use TorMorten\Deck\Support\UnprocessedQueueDetector;

#[Layout('deck::layouts.app')]
class Dashboard extends Component
{
    use InteractsWithExecutions;

    public function render()
    {
        $scope = JobClassStat::query()->forInstallation();
        $metrics = ExecutionMetrics::make();
        $horizon = app(HorizonSnapshot::class);
        $horizonSummary = $horizon->summary();
        $queueBusyness = app(QueueBusyness::class)->assess();
        $unprocessedQueues = app(UnprocessedQueueDetector::class)->detect();

        $recentFailures = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Failed)
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $summary = [
            'classes' => (clone $scope)->count(),
            'running' => (clone $scope)->where('last_status', JobExecutionStatus::Running)->count(),
            'failed' => (clone $scope)->where('last_status', JobExecutionStatus::Failed)->count(),
            'successes' => (int) (clone $scope)->sum('success_count'),
            'executions' => JobExecution::query()->forInstallation()->count(),
        ];

        $allClear = $recentFailures->isEmpty()
            && $unprocessedQueues->isEmpty()
            && $summary['running'] === 0
            && ! in_array($queueBusyness['level'], [QueueBusynessLevel::Busy, QueueBusynessLevel::Critical], true)
            && ($horizonSummary === null || ($horizonSummary['status'] ?? null) === 'running');

        return view('deck::livewire.dashboard', [
            'summary' => $summary,
            'jobVolumeChart' => $metrics->hourlyJobVolume()->all(),
            'durationChart' => $metrics->hourlyAverageDuration()->all(),
            'queueBusyness' => $queueBusyness,
            'unprocessedQueues' => $unprocessedQueues,
            'horizonSummary' => $horizonSummary,
            'horizonAvailable' => $horizon->isAvailable(),
            'recentFailures' => $recentFailures,
            'recentFailureCount' => $recentFailures->count(),
            'allClear' => $allClear,
            'shouldPoll' => $summary['running'] > 0 || $horizon->isAvailable(),
            'horizonUrl' => DeckHorizon::dashboardUrl(),
        ]);
    }
}
