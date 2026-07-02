<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\Agent\AgentSync;
use Deck\Deck\Cloud\Connection\CloudConnectionProbe;
use Deck\Deck\Cloud\DeckCloud;
use Deck\Deck\Cloud\Workers\WorkerReporter;
use Deck\Deck\Cloud\Workers\WorkerSnapshotCollector;
use Deck\Deck\Horizon\DeckHorizon;
use Illuminate\Console\Command;

class ReportWorkersCommand extends Command
{
    protected $signature = 'deck:report-workers';

    protected $description = 'Report queue worker snapshots to Deck Cloud';

    public function handle(): int
    {
        if (! DeckCloud::isEnabled()) {
            $this->components->warn('Deck Cloud is disabled (set DECK_API_KEY, or remove DECK_CLOUD_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! DeckCloud::workersEnabled()) {
            $this->components->warn('Deck Cloud worker reporting is disabled (DECK_CLOUD_WORKERS_ENABLED=false).');

            return self::SUCCESS;
        }

        $collector = app(WorkerSnapshotCollector::class);
        $fromHorizon = $collector->collectFromHorizon();
        $workers = $fromHorizon !== [] ? $fromHorizon : $collector->collectFallbackQueueWorkers();
        $queues = $collector->collectWorkloadFromHorizon();

        if ($workers === [] && $queues === []) {
            // A benign steady state (sync queue, or Horizon not up yet), not a failure.
            // Returning non-zero here makes the every-minute scheduler raise
            // ScheduledTaskFailed each tick and spam error reporters (Flare, etc.).
            $this->components->warn('Nothing to report — Horizon has no supervisors and no queue workload was found.');

            if (DeckHorizon::isInstalled()) {
                $this->line('Is `php artisan horizon` running on this server and using the same Redis as this app?');
            } else {
                $this->line('Set QUEUE_CONNECTION to a non-sync driver, or install Horizon for richer worker snapshots.');
            }

            return self::SUCCESS;
        }

        if ($fromHorizon === [] && $workers !== []) {
            $this->components->warn('Horizon returned no supervisors; reported a fallback snapshot for the default queue connection instead.');
        }

        $accepted = app(AgentSync::class)->reportCollected($workers, $queues, force: true);

        app(CloudConnectionProbe::class)->forget();

        if (! WorkerReporter::lastSendAttempted()) {
            // Throttling is expected under the every-minute schedule — not a failure.
            $this->components->warn('Worker snapshots were not sent (throttled or empty payload).');

            return self::SUCCESS;
        }

        if (! $accepted) {
            $this->components->error('Deck Cloud rejected or did not accept worker snapshots. Check laravel.log for "Deck Cloud" warnings (401, 422, 5xx).');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Worker snapshots accepted by Deck Cloud (%d worker(s), %d queue metric(s)).',
            count($workers),
            count($queues),
        ));

        return self::SUCCESS;
    }
}
