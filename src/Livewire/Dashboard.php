<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Enums\QueueBusynessLevel;
use Deck\Deck\Livewire\Concerns\InteractsWithExecutions;
use Deck\Deck\Models\JobClassStat;
use Deck\Deck\Models\JobExecution;
use Deck\Deck\Support\DeckHorizon;
use Deck\Deck\Support\DeckPolling;
use Deck\Deck\Support\ExecutionMetrics;
use Deck\Deck\Support\HorizonSnapshot;
use Deck\Deck\Support\QueueBusyness;
use Deck\Deck\Support\UnprocessedQueueDetector;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('deck::layouts.app')]
class Dashboard extends Component
{
    use InteractsWithExecutions;

    public function render(): View
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
            && ($horizonSummary === null || $horizonSummary['status'] === 'running');

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
            'pollSeconds' => DeckPolling::dashboardSeconds($summary['running']),
            'horizonUrl' => DeckHorizon::dashboardUrl(),
        ]);
    }
}
