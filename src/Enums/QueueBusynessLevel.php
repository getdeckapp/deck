<?php

namespace TorMorten\Deck\Enums;

enum QueueBusynessLevel: string
{
    case Idle = 'idle';
    case Light = 'light';
    case Moderate = 'moderate';
    case Busy = 'busy';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Light => 'Light',
            self::Moderate => 'Moderate',
            self::Busy => 'Busy',
            self::Critical => 'Critical',
        };
    }
}
