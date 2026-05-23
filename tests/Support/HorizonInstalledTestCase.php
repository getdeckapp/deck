<?php

namespace Deck\Deck\Tests\Support;

use Deck\Deck\Tests\TestCase;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\Supervisor;

class HorizonInstalledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->singleton(WorkloadRepository::class, fn (): WorkloadRepository => new class implements WorkloadRepository
        {
            public function get(): array
            {
                return [];
            }
        });

        $app->singleton(MasterSupervisorRepository::class, fn (): MasterSupervisorRepository => new class implements MasterSupervisorRepository
        {
            public function names(): array
            {
                return [];
            }

            public function all(): array
            {
                return [];
            }

            public function find($name)
            {
                return null;
            }

            public function get(array $names): array
            {
                return [];
            }

            public function update(MasterSupervisor $master): void {}

            public function forget($name): void {}

            public function flushExpired(): void {}
        });

        $app->singleton(SupervisorRepository::class, fn (): SupervisorRepository => new class implements SupervisorRepository
        {
            public function names(): array
            {
                return [];
            }

            public function all(): array
            {
                return [];
            }

            public function find($name)
            {
                return null;
            }

            public function get(array $names): array
            {
                return [];
            }

            public function longestActiveTimeout(): int
            {
                return 0;
            }

            public function update(Supervisor $supervisor): void {}

            public function forget($names): void {}

            public function flushExpired(): void {}
        });

        $app->singleton(MetricsRepository::class, fn (): MetricsRepository => new class implements MetricsRepository
        {
            public function jobsProcessedPerMinute(): int
            {
                return 0;
            }

            public function throughputForQueue(string $queue): int
            {
                return 0;
            }
        });
    }
}
