<?php

namespace TorMorten\Deck\Support;

use Illuminate\Support\Collection;
use TorMorten\Deck\Data\DeckStaleJobAlert;
use TorMorten\Deck\Models\JobClassStat;

class DeckAlertChecker
{
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
}
