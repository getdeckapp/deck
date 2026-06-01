<?php

namespace Deck\Deck\Cloud\Connection;

enum CloudConnectionState: string
{
    case Disabled = 'disabled';
    case Connected = 'connected';
    case Unauthorized = 'unauthorized';
    case Misconfigured = 'misconfigured';
    case Unreachable = 'unreachable';
}
