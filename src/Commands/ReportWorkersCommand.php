<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\AgentSync;
use Illuminate\Console\Command;

class ReportWorkersCommand extends Command
{
    protected $signature = 'deck:report-workers';

    protected $description = 'Report queue worker snapshots to Deck Cloud';

    public function handle(AgentSync $sync): int
    {
        if (! AgentSync::isEnabled()) {
            $this->components->warn('Deck Cloud worker reporting is disabled.');

            return self::SUCCESS;
        }

        $sync->report();

        $this->components->info('Worker snapshots sent to Deck Cloud.');

        return self::SUCCESS;
    }
}
