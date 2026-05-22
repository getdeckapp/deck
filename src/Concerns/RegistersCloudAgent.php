<?php

namespace Deck\Deck\Concerns;

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\CloudAgentRegistry;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Listeners\SyncCloudAgent;
use Deck\Deck\Support\DeckHorizon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

trait RegistersCloudAgent
{
    protected function registerCloudAgent(): void
    {
        CloudAgentRegistry::register($this->app);
    }

    protected function bootCloudAgent(): void
    {
        if (! AgentSync::isEnabled()) {
            return;
        }

        $listener = $this->app->make(SyncCloudAgent::class);

        if (DeckHorizon::isInstalled()) {
            Event::listen(
                'Laravel\Horizon\Events\MasterSupervisorLooped',
                [$listener, 'onHorizonLoop'],
            );

            return;
        }

        // Sync for queue:work / queue:listen when Horizon is not installed.
        Queue::looping(fn (Looping $event) => $listener->onQueueLoop($event));
    }

    protected function scheduleCloudAgent(): void
    {
        if (! DeckCloud::isEnabled()) {
            return;
        }

        $interval = DeckCloud::syncIntervalSeconds();

        $this->app->booted(function () use ($interval): void {
            $schedule = $this->app->make(Schedule::class);

            if (DeckCloud::workersEnabled()) {
                $event = match (true) {
                    $interval >= 60 => $schedule->command('deck:report-workers')->everyMinute(),
                    $interval >= 30 => $schedule->command('deck:report-workers')->everyThirtySeconds(),
                    default => $schedule->command('deck:report-workers')->everyTenSeconds(),
                };

                $event->withoutOverlapping();
            }

            if (DeckCloud::commandsEnabled()) {
                $event = match (true) {
                    $interval >= 60 => $schedule->command('deck:poll-commands')->everyMinute(),
                    $interval >= 30 => $schedule->command('deck:poll-commands')->everyThirtySeconds(),
                    default => $schedule->command('deck:poll-commands')->everyTenSeconds(),
                };

                $event->withoutOverlapping();
            }
        });
    }
}
