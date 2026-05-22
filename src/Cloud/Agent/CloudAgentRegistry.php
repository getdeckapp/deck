<?php

namespace Deck\Deck\Cloud\Agent;

use Deck\Deck\Cloud\Commands\CommandApplicator;
use Deck\Deck\Cloud\Commands\CommandPoller;
use Deck\Deck\Cloud\Connection\HttpClient;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Workers\WorkerReporter;
use Deck\Deck\Cloud\Workers\WorkerSnapshotCollector;
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
