<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\DeckCloud;
use Illuminate\Console\Command;

class ReportWorkersCommand extends Command
{
    protected $signature = 'deck:report-workers';

    protected $description = 'Report queue worker snapshots to Deck Cloud';

    public function handle(): int
    {
        if (! DeckCloud::isEnabled()) {
            $this->components->warn('Deck Cloud worker reporting is disabled.');

            return self::SUCCESS;
        }

        app(AgentSync::class)->report();

        $this->components->info('Worker snapshots sent to Deck Cloud.');

        return self::SUCCESS;
    }
}
