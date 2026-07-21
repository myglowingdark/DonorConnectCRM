<?php

namespace App\Enums;

enum TrackingEventType: string
{
    case Sent = 'sent';
    case Opened = 'opened';
    case PageView = 'page_view';
    case CheckoutStarted = 'checkout_started';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Opened => 'Opened',
            self::PageView => 'Page view',
            self::CheckoutStarted => 'Checkout started',
            self::Paid => 'Paid',
        };
    }
}
