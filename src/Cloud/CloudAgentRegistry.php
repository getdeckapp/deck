<?php

namespace Deck\Deck\Cloud;

use Illuminate\Contracts\Foundation\Application;

/**
 * Registers Deck Cloud agent services when {@see DeckCloud::isEnabled()} is true.
 */
class CloudAgentRegistry
{
    public static function register(Application $app): void
    {
        if (! DeckCloud::isEnabled()) {
            return;
        }

        $app->singleton(HttpClient::class);
        $app->singleton(SyncThrottle::class);
        $app->singleton(WorkerSnapshotCollector::class);
        $app->singleton(WorkerReporter::class);
        $app->singleton(CommandApplicator::class);
        $app->singleton(CommandPoller::class);
        $app->singleton(AgentSync::class);
    }
}
