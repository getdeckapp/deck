<?php

namespace TorMorten\Deck\Commands;

use Illuminate\Console\Command;

class DeckCommand extends Command
{
    public $signature = 'deck';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
