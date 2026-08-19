<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The delivery channels a campaign can use (doc 07 §3).
 *
 * SMS reaches only recipients who opted in and have a number (decision D4);
 * the channel being selected on a message is permission to try, never a
 * promise that every recipient gets one.
 */
enum MessageChannel: string implements HasColor, HasLabel
{
    case Email = 'email';
    case Sms = 'sms';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => __('Email'),
            self::Sms => __('SMS'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Email => 'info',
            self::Sms => 'success',
        };
    }
}
