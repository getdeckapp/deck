<?php

namespace Deck\Deck\Support;

use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\WaitTimeCalculator;

class HorizonSnapshot
{
    public function __construct(
        private readonly bool $installed,
        private readonly mixed $workload = null,
        private readonly mixed $masters = null,
        private readonly mixed $supervisors = null,
        private readonly mixed $metrics = null,
    ) {}

    public static function make(): self
    {
        if (! DeckHorizon::isInstalled()) {
            return new self(installed: false);
        }

        return new self(
            installed: true,
            workload: app(WorkloadRepository::class),
            masters: app(MasterSupervisorRepository::class),
            supervisors: app(SupervisorRepository::class),
            metrics: app(MetricsRepository::class),
        );
    }

    public function isAvailable(): bool
    {
        return $this->installed;
    }

    /**
     * @return list<array{name: string, length: int, wait: int|float, processes: int}>
     */
    public function workload(): array
    {
        if (! $this->workload) {
            return [];
        }

        return collect($this->workload->get())
            ->map(fn (array $queue): array => [
                'name' => (string) $queue['name'],
                'length' => (int) ($queue['length'] ?? 0),
                'wait' => $queue['wait'] ?? 0,
                'processes' => (int) ($queue['processes'] ?? 0),
            ])
            ->sortByDesc('length')
            ->values()
            ->all();
    }

    /**
     * @return array{status: string, processes: int, jobs_per_minute: int, paused_masters: int, wait: array<string, int|float>}|null
     */
    public function summary(): ?array
    {
        if (! $this->installed || ! $this->masters || ! $this->metrics) {
            return null;
        }

        $masters = collect($this->masters->all());
        $wait = collect(app(WaitTimeCalculator::class)->calculate())
            ->map(fn ($value) => (int) $value);

        return [
            'status' => $this->horizonStatus($masters),
            'processes' => $this->totalProcessCount(),
            'jobs_per_minute' => (int) $this->metrics->jobsProcessedPerMinute(),
            'paused_masters' => $masters->filter(fn ($master) => $master->status === 'paused')->count(),
            'wait' => $wait->all(),
        ];
    }

    /**
     * @return list<array{name: string, master: string, status: string, pid: string|int|null, processes: int, queues: list<array{name: string, processes: int}>}>
     */
    public function supervisors(): array
    {
        if (! $this->supervisors) {
            return [];
        }

        return collect($this->supervisors->all())
            ->map(function ($supervisor): array {
                $processMap = is_array($supervisor->processes) ? $supervisor->processes : [];

                return [
                    'name' => (string) $supervisor->name,
                    'master' => (string) $supervisor->master,
                    'status' => (string) $supervisor->status,
                    'pid' => $supervisor->pid ?? null,
                    'processes' => (int) array_sum($processMap),
                    'queues' => collect($processMap)
                        ->map(fn (int $count, string $name): array => [
                            'name' => $name,
                            'processes' => $count,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, status: string, supervisors: int, processes: int}>
     */
    public function masters(): array
    {
        if (! $this->masters || ! $this->supervisors) {
            return [];
        }

        $supervisors = collect($this->supervisors->all())->groupBy('master');

        return collect($this->masters->all())
            ->map(function ($master) use ($supervisors) {
                $masterSupervisors = $supervisors->get($master->name, collect());
                $processes = $masterSupervisors->sum(
                    fn ($supervisor) => collect($supervisor->processes)->sum()
                );

                return [
                    'name' => (string) $master->name,
                    'status' => (string) $master->status,
                    'supervisors' => $masterSupervisors->count(),
                    'processes' => (int) $processes,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function horizonStatus(Collection $masters): string
    {
        if ($masters->isEmpty()) {
            return 'inactive';
        }

        return $masters->every(fn ($master) => $master->status === 'paused') ? 'paused' : 'running';
    }

    private function totalProcessCount(): int
    {
        if (! $this->supervisors) {
            return 0;
        }

        return collect($this->supervisors->all())
            ->sum(fn ($supervisor) => collect($supervisor->processes)->sum());
    }
}
