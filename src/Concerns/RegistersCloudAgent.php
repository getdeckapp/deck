<?php

namespace Deck\Deck\Concerns;

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\CommandApplicator;
use Deck\Deck\Cloud\CommandPoller;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\HttpClient;
use Deck\Deck\Cloud\SyncThrottle;
use Deck\Deck\Cloud\WorkerReporter;
use Deck\Deck\Cloud\WorkerSnapshotCollector;
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
        $this->app->singleton(HttpClient::class);
        $this->app->singleton(SyncThrottle::class);
        $this->app->singleton(WorkerSnapshotCollector::class);
        $this->app->singleton(WorkerReporter::class);
        $this->app->singleton(CommandApplicator::class);
        $this->app->singleton(CommandPoller::class);
        $this->app->singleton(AgentSync::class);
    }

    protected function bootCloudAgent(): void
    {
        if (! AgentSync::isEnabled() || ! $this->app->runningInConsole()) {
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

        Queue::looping(fn (Looping $event) => $listener->onQueueLoop($event));
    }

    protected function scheduleCloudAgent(): void
    {
        if (! AgentSync::isEnabled()) {
            return;
        }

        $interval = DeckCloud::syncIntervalSeconds();

        $this->app->booted(function () use ($interval): void {
            $schedule = $this->app->make(Schedule::class);

            if ($interval >= 60) {
                $event = $schedule->command('deck:report-workers')->everyMinute();
            } elseif ($interval >= 30) {
                $event = $schedule->command('deck:report-workers')->everyThirtySeconds();
            } else {
                $event = $schedule->command('deck:report-workers')->everyTenSeconds();
            }

            $event->withoutOverlapping();
        });
    }
}
