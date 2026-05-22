<?php

namespace Deck\Deck\Tests\Support;

use Deck\Deck\Tests\TestCase;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

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
            public function all(): array
            {
                return [];
            }
        });

        $app->singleton(SupervisorRepository::class, fn (): SupervisorRepository => new class implements SupervisorRepository
        {
            public function all(): array
            {
                return [];
            }
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
