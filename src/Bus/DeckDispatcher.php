<?php

namespace Deck\Deck\Bus;

use Deck\Deck\Support\InterceptBlockedDispatch;
use Illuminate\Bus\Dispatcher;

class DeckDispatcher extends Dispatcher
{
    /**
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchToQueue($command)
    {
        if (InterceptBlockedDispatch::intercept($command)) {
            return null;
        }

        return parent::dispatchToQueue($command);
    }
}
