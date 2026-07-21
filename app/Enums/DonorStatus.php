<?php

namespace App\Enums;

enum DonorStatus: string
{
    case New = 'new';
    case FollowUp = 'follow_up';
    case Interested = 'interested';
    case Pledged = 'pledged';
    case Donated = 'donated';
    case NotInterested = 'not_interested';
    case DoNotCall = 'do_not_call';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::FollowUp => 'Follow-up',
            self::Interested => 'Interested',
            self::Pledged => 'Pledged',
            self::Donated => 'Donated',
            self::NotInterested => 'Not Interested',
            self::DoNotCall => 'Do Not Call',
        };
    }

    public static function fromOutcome(CallOutcome $outcome): self
    {
        return match ($outcome) {
            CallOutcome::Interested => self::Interested,
            CallOutcome::NotInterested => self::NotInterested,
            CallOutcome::Pledged => self::Pledged,
            CallOutcome::Donated => self::Donated,
            CallOutcome::DoNotCall => self::DoNotCall,
            CallOutcome::CallbackRequested => self::FollowUp,
            default => self::FollowUp,
        };
    }
}
