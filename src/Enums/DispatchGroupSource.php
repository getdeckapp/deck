<?php

namespace Deck\Deck\Enums;

enum DispatchGroupSource: string
{
    case Request = 'request';
    case Lineage = 'lineage';
    case Manual = 'manual';
    case Batch = 'batch';
}
