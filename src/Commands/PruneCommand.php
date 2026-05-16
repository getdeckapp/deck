<?php

namespace TorMorten\Deck\Commands;

use Illuminate\Console\Command;
use TorMorten\Deck\Models\JobExecution;

class PruneCommand extends Command
{
    protected $signature = 'deck:prune';

    protected $description = 'Prune Deck job execution history older than the configured retention period';

    public function handle(): int
    {
        $days = config('deck.retention_days', 90);

        $deleted = JobExecution::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->components->info("Pruned {$deleted} job execution(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
