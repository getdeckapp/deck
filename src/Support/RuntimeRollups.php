<?php

namespace TorMorten\Deck\Support;

use Illuminate\Support\Collection;
use TorMorten\Deck\Data\RuntimeRollup;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobExecution;

class RuntimeRollups
{
    public function __construct(
        private readonly int $hours = 24,
    ) {}

    public static function make(): self
    {
        return new self((int) config('deck.charts.hours', 24));
    }

    public function forJobClass(string $jobClass): RuntimeRollup
    {
        $since = now()->subHours($this->hours);

        $executions = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $jobClass)
            ->where('started_at', '>=', $since)
            ->get(['status', 'duration_ms']);

        $finished = $executions->filter(
            fn (JobExecution $execution): bool => in_array($execution->status, [
                JobExecutionStatus::Completed,
                JobExecutionStatus::Failed,
                JobExecutionStatus::Cancelled,
            ], true),
        );

        $completedCount = $finished->where('status', JobExecutionStatus::Completed)->count();
        $failedCount = $finished->where('status', JobExecutionStatus::Failed)->count();
        $terminalCount = $completedCount + $failedCount;

        $durations = $finished
            ->whereNotNull('duration_ms')
            ->pluck('duration_ms')
            ->map(fn (mixed $ms): int => (int) $ms)
            ->sort()
            ->values();

        $failureRate = $terminalCount > 0
            ? round(($failedCount / $terminalCount) * 100, 1)
            : null;

        return new RuntimeRollup(
            sampleCount: $durations->count(),
            avgMs: $this->average($durations),
            p50Ms: $this->percentile($durations, 50),
            p95Ms: $this->percentile($durations, 95),
            failureRate: $failureRate,
            completedCount: $completedCount,
            failedCount: $failedCount,
        );
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function average(Collection $values): ?int
    {
        if ($values->isEmpty()) {
            return null;
        }

        return (int) round($values->avg());
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function percentile(Collection $values, int $percentile): ?int
    {
        if ($values->isEmpty()) {
            return null;
        }

        $index = (int) ceil(($percentile / 100) * $values->count()) - 1;
        $index = max(0, min($index, $values->count() - 1));

        return $values->get($index);
    }
}
