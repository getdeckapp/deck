<?php

namespace Deck\Deck\Presentation;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Deck\Deck\Models\JobExecution;
use Illuminate\Support\Collection;

class ExecutionMetrics
{
    public function __construct(
        private readonly int $hours = 24,
    ) {}

    public static function make(): self
    {
        return new self((int) config('deck.charts.hours', 24));
    }

    /**
     * @return Collection<int, array{label: string, value: int, at: string}>
     */
    public function hourlyJobVolume(): Collection
    {
        $since = now()->subHours($this->hours)->startOfHour();

        $counts = JobExecution::query()
            ->forInstallation()
            ->where('started_at', '>=', $since)
            ->select('started_at')
            ->get()
            ->groupBy(fn (JobExecution $execution): string => $execution->started_at->copy()->startOfHour()->format('Y-m-d H:00'))
            ->map(fn (Collection $group): int => $group->count());

        return $this->fillHourlySeries($since, $counts);
    }

    /**
     * @return Collection<int, array{label: string, value: int, at: string}>
     */
    public function hourlyJobVolumeForClass(string $jobClass): Collection
    {
        $since = now()->subHours($this->hours)->startOfHour();

        $counts = JobExecution::query()
            ->forInstallation()
            ->where('job_class', $jobClass)
            ->where('started_at', '>=', $since)
            ->get()
            ->groupBy(fn (JobExecution $execution): string => $execution->started_at->copy()->startOfHour()->format('Y-m-d H:00'))
            ->map(fn (Collection $group): int => $group->count());

        return $this->fillHourlySeries($since, $counts);
    }

    /**
     * @return Collection<int, array{label: string, value: int, at: string}>
     */
    public function hourlyFailedVolume(): Collection
    {
        $since = now()->subHours($this->hours)->startOfHour();

        $counts = JobExecution::query()
            ->forInstallation()
            ->where('status', \Deck\Deck\Enums\JobExecutionStatus::Failed)
            ->where('started_at', '>=', $since)
            ->select('started_at')
            ->get()
            ->groupBy(fn (JobExecution $execution): string => $execution->started_at->copy()->startOfHour()->format('Y-m-d H:00'))
            ->map(fn (Collection $group): int => $group->count());

        return $this->fillHourlySeries($since, $counts);
    }

    /**
     * @return Collection<int, array{label: string, value: int, at: string}>
     */
    public function hourlyAverageDuration(): Collection
    {
        $since = now()->subHours($this->hours)->startOfHour();

        $averages = JobExecution::query()
            ->forInstallation()
            ->where('started_at', '>=', $since)
            ->whereNotNull('duration_ms')
            ->select(['started_at', 'duration_ms'])
            ->get()
            ->groupBy(fn (JobExecution $execution): string => $execution->started_at->copy()->startOfHour()->format('Y-m-d H:00'))
            ->map(fn (Collection $group): int => (int) round($group->avg('duration_ms')));

        return $this->fillHourlySeries($since, $averages);
    }

    /**
     * @param  Collection<int|string, int>  $values
     * @return Collection<int, array{label: string, value: int, at: string}>
     */
    private function fillHourlySeries(CarbonInterface $since, Collection $values): Collection
    {
        $period = CarbonPeriod::create($since, '1 hour', now()->startOfHour());
        $points = collect();

        foreach ($period as $hour) {
            $key = $hour->format('Y-m-d H:00');

            $points->push([
                'label' => $hour->format('H:i'),
                'value' => (int) $values->get($key, 0),
                'at' => $key,
            ]);
        }

        return $points;
    }
}
