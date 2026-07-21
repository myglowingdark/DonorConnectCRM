<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Logged = 'logged';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Logged => 'Logged (no provider)',
            self::Failed => 'Failed',
        };
    }
}
