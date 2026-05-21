<?php

namespace Deck\Deck\Commands;

use Deck\Deck\Cloud\AgentSync;
use Deck\Deck\Cloud\DeckCloud;
use Illuminate\Console\Command;

class PollCommandsCommand extends Command
{
    protected $signature = 'deck:poll-commands';

    protected $description = 'Pull and apply remote commands from Deck Cloud';

    public function handle(): int
    {
        if (! DeckCloud::isEnabled()) {
            $this->components->warn('Deck Cloud is disabled (set DECK_API_KEY, or remove DECK_CLOUD_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! DeckCloud::commandsEnabled()) {
            $this->components->warn('Deck Cloud command polling is disabled (DECK_CLOUD_COMMANDS_ENABLED=false).');

            return self::SUCCESS;
        }

        app(AgentSync::class)->pollCommands();

        $this->components->info('Polled Deck Cloud for remote commands.');

        return self::SUCCESS;
    }
}
