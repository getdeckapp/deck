<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\Agent\AgentSync;
use Deck\Deck\Cloud\Connection\CloudConnectionProbe;
use Deck\Deck\Cloud\DeckCloud;
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

        app(AgentSync::class)->report();

        app(CloudConnectionProbe::class)->forget();

        $this->components->info('Worker snapshots sent to Deck Cloud.');

        return self::SUCCESS;
    }
}
