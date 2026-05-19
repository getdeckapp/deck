<?php

namespace Deck\Deck\Support;

use Deck\Deck\Enums\JobExecutionStatus;
use Deck\Deck\Models\JobExecution;
use Illuminate\Support\Collection;

class QueueInsights
{
    /**
     * Queue activity derived from Deck's durable execution log (no Horizon required).
     *
     * @return Collection<int, array{queue: string, running: int, completed_last_hour: int, avg_duration_ms: int|null, load: int}>
     */
    public function busyQueues(): Collection
    {
        $running = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Running)
            ->selectRaw('queue, count(*) as total')
            ->groupBy('queue')
            ->pluck('total', 'queue');

        $completedLastHour = JobExecution::query()
            ->forInstallation()
            ->where('status', JobExecutionStatus::Completed)
            ->where('started_at', '>=', now()->subHour())
            ->selectRaw('queue, count(*) as total')
            ->groupBy('queue')
            ->pluck('total', 'queue');

        $avgDuration = JobExecution::query()
            ->forInstallation()
            ->whereNotNull('duration_ms')
            ->where('started_at', '>=', now()->subDay())
            ->selectRaw('queue, avg(duration_ms) as avg_duration')
            ->groupBy('queue')
            ->pluck('avg_duration', 'queue');

        $queues = $running->keys()
            ->merge($completedLastHour->keys())
            ->merge($avgDuration->keys())
            ->unique()
            ->sort()
            ->values();

        return $queues->map(function (string $queue) use ($running, $completedLastHour, $avgDuration) {
            $runningCount = (int) ($running[$queue] ?? 0);
            $completedCount = (int) ($completedLastHour[$queue] ?? 0);
            $load = ($runningCount * 10) + $completedCount;

            return [
                'queue' => $queue,
                'running' => $runningCount,
                'completed_last_hour' => $completedCount,
                'avg_duration_ms' => isset($avgDuration[$queue]) ? (int) round((float) $avgDuration[$queue]) : null,
                'load' => $load,
            ];
        })->sortByDesc('load')->values();
    }
}
