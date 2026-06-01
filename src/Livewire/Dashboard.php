<?php

namespace Deck\Deck\Livewire;

use Deck\Deck\Cloud\CloudConnectionProbe;
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
            'classes' => JobClassStat::query()->forInstallation()->count(),
            'running' => JobClassStat::query()->forInstallation()->where('last_status', JobExecutionStatus::Running)->count(),
            'failed' => JobClassStat::query()->forInstallation()->where('last_status', JobExecutionStatus::Failed)->count(),
            'successes' => (int) JobClassStat::query()->forInstallation()->sum('success_count'),
            'executions' => JobExecution::query()->forInstallation()->count(),
        ];

        $failedToday = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Failed)
            ->where('started_at', '>=', now()->startOfDay())
            ->count();

        $failedYesterday = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Failed)
            ->whereBetween('started_at', [now()->subDay()->startOfDay(), now()->startOfDay()])
            ->count();

        $completedToday = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Completed)
            ->where('started_at', '>=', now()->startOfDay())
            ->count();

        $failedDelta = null;
        $failedDeltaPositive = null;
        if ($failedToday > 0 || $failedYesterday > 0) {
            $diff = abs($failedToday - $failedYesterday);
            if ($failedToday > $failedYesterday) {
                $failedDelta = '↑'.$diff.' from yesterday';
                $failedDeltaPositive = true;
            } elseif ($failedToday < $failedYesterday) {
                $failedDelta = '↓'.$diff.' from yesterday';
                $failedDeltaPositive = false;
            } else {
                $failedDelta = 'Same as yesterday';
                $failedDeltaPositive = null;
            }
        }

        $jobVolumeChart = $metrics->hourlyJobVolume()->all();
        $failedSparkline = $metrics->hourlyFailedVolume()->pluck('value')->slice(-14)->values()->all();
        $volumeSparkline = collect($jobVolumeChart)->pluck('value')->slice(-14)->values()->all();

        $allClear = $recentFailures->isEmpty()
            && $unprocessedQueues->isEmpty()
            && $summary['running'] === 0
            && ! in_array($queueBusyness['level'], [QueueBusynessLevel::Busy, QueueBusynessLevel::Critical], true)
            && ($horizonSummary === null || $horizonSummary['status'] === 'running');

        return view('deck::livewire.dashboard', [
            'summary' => $summary,
            'failedToday' => $failedToday,
            'failedDelta' => $failedDelta,
            'failedDeltaPositive' => $failedDeltaPositive,
            'completedToday' => $completedToday,
            'failedSparkline' => $failedSparkline,
            'volumeSparkline' => $volumeSparkline,
            'jobVolumeChart' => $jobVolumeChart,
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
            'deckCloudConnection' => app(CloudConnectionProbe::class)->status(),
        ]);
    }
}
