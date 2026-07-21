<?php

namespace App\Enums;

enum AttributionSource: string
{
    case Call = 'call';
    case TrackingLink = 'tracking_link';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::TrackingLink => 'Tracking link',
        };
    }
}
