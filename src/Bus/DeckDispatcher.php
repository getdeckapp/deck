<?php

namespace TorMorten\Deck\Bus;

use Illuminate\Bus\Dispatcher;
use TorMorten\Deck\Support\InterceptBlockedDispatch;

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
