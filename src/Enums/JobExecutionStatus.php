<?php

namespace TorMorten\Deck\Enums;

enum JobExecutionStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';
}
