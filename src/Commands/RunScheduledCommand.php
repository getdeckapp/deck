<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\DeckCloud;
use Illuminate\Console\Command;

class RunScheduledCommand extends Command
{
    protected $signature = 'deck:run-scheduled';

    protected $description = 'Run all of Deck\'s scheduled maintenance commands in one pass';

    public function handle(): int
    {
        $exitCode = self::SUCCESS;

        foreach ($this->scheduledCommands() as $command => $enabled) {
            if (! $enabled) {
                $this->components->twoColumnDetail($command, '<fg=yellow>SKIPPED</>');

                continue;
            }

            $this->components->info("Running {$command}...");

            if ($this->call($command) !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    /**
     * The recurring Deck commands, keyed by signature, with whether the current
     * configuration enables them. Disabled commands are skipped rather than run.
     *
     * @return array<string, bool>
     */
    private function scheduledCommands(): array
    {
        return [
            'deck:prune' => true,
            'deck:check-alerts' => (bool) config('deck.alerts.enabled', false),
            'deck:report-workers' => DeckCloud::workersEnabled(),
            'deck:poll-commands' => DeckCloud::commandsEnabled(),
        ];
    }
}
