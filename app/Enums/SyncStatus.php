<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Idle = 'idle';
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }
}
