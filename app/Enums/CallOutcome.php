<?php

namespace App\Enums;

enum CallOutcome: string
{
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case CallbackRequested = 'callback_requested';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case Pledged = 'pledged';
    case Donated = 'donated';
    case WrongNumber = 'wrong_number';
    case DoNotCall = 'do_not_call';

    public function label(): string
    {
        return match ($this) {
            self::NoAnswer => 'No Answer',
            self::Busy => 'Busy',
            self::CallbackRequested => 'Callback Requested',
            self::Interested => 'Interested',
            self::NotInterested => 'Not Interested',
            self::Pledged => 'Pledged',
            self::Donated => 'Donated',
            self::WrongNumber => 'Wrong Number',
            self::DoNotCall => 'Do Not Call',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NoAnswer => 'phone_missed',
            self::Busy => 'phone_paused',
            self::CallbackRequested => 'schedule_callback',
            self::Interested => 'thumb_up',
            self::NotInterested => 'thumb_down',
            self::Pledged => 'handshake',
            self::Donated => 'volunteer_activism',
            self::WrongNumber => 'phonelink_erase',
            self::DoNotCall => 'do_not_disturb_on',
        };
    }
}
