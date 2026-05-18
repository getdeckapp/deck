<?php

namespace TorMorten\Deck\Support;

use Illuminate\Support\Collection;
use TorMorten\Deck\Data\DeckFailureRateAlert;
use TorMorten\Deck\Data\DeckStaleJobAlert;
use TorMorten\Deck\Data\DeckUnprocessedQueueAlert;
use TorMorten\Deck\Enums\JobExecutionStatus;
use TorMorten\Deck\Models\JobClassStat;
use TorMorten\Deck\Models\JobExecution;

class DeckAlertChecker
{
    public function __construct(
        private readonly UnprocessedQueueDetector $unprocessedQueueDetector,
    ) {}

    /**
     * @return Collection<int, DeckStaleJobAlert>
     */
    public function staleJobs(): Collection
    {
        if (! config('deck.alerts.enabled', false)) {
            return collect();
        }

        $rules = config('deck.alerts.stale_jobs', []);

        if ($rules === []) {
            return collect();
        }

        return collect($rules)->map(function (array $rule, string $jobClass): ?DeckStaleJobAlert {
            $maxAgeHours = (int) ($rule['max_age_hours'] ?? 24);

            $stat = JobClassStat::query()
                ->forInstallation()
                ->where('job_class', $jobClass)
                ->first();

            $lastFinishedAt = $stat?->last_finished_at;

            if ($lastFinishedAt !== null && $lastFinishedAt->gte(now()->subHours($maxAgeHours))) {
                return null;
            }

            return new DeckStaleJobAlert(
                jobClass: $jobClass,
                maxAgeHours: $maxAgeHours,
                lastFinishedAt: $lastFinishedAt,
            );
        })->filter()->values();
    }

    /**
     * @return Collection<int, DeckFailureRateAlert>
     */
    public function failureRates(): Collection
    {
        if (! config('deck.alerts.enabled', false)) {
            return collect();
        }

        $rules = config('deck.alerts.failure_rate_jobs', []);

        if ($rules === []) {
            return collect();
        }

        return collect($rules)->map(function (array $rule, string $jobClass): ?DeckFailureRateAlert {
            $maxFailureRate = (float) ($rule['max_failure_rate'] ?? 10);
            $windowHours = max(1, (int) ($rule['window_hours'] ?? 24));
            $minSamples = max(1, (int) ($rule['min_samples'] ?? 5));

            $since = now()->subHours($windowHours);

            $executions = JobExecution::query()
                ->forInstallation()
                ->where('job_class', $jobClass)
                ->where('started_at', '>=', $since)
                ->whereIn('status', [JobExecutionStatus::Completed, JobExecutionStatus::Failed])
                ->get(['status']);

            $sampleCount = $executions->count();

            if ($sampleCount < $minSamples) {
                return null;
            }

            $failedCount = $executions->where('status', JobExecutionStatus::Failed)->count();
            $failureRate = round(($failedCount / $sampleCount) * 100, 1);

            if ($failureRate <= $maxFailureRate) {
                return null;
            }

            return new DeckFailureRateAlert(
                jobClass: $jobClass,
                failureRate: $failureRate,
                maxFailureRate: $maxFailureRate,
                windowHours: $windowHours,
                sampleCount: $sampleCount,
                failedCount: $failedCount,
            );
        })->filter()->values();
    }

    /**
     * @return Collection<int, DeckUnprocessedQueueAlert>
     */
    public function unprocessedQueues(): Collection
    {
        if (! config('deck.alerts.enabled', false)) {
            return collect();
        }

        if (! config('deck.unprocessed_queues.include_alerts', true)) {
            return collect();
        }

        return $this->unprocessedQueueDetector
            ->detect()
            ->map(fn ($queue) => new DeckUnprocessedQueueAlert($queue))
            ->values();
    }
}
