<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What an approved grant is worth. Chosen by the coordinator at approval time,
 * never by the applicant.
 *
 * `PercentOff` at 100 is free in effect, but the two cases stay distinct
 * because the coordinator's choice is what gets reported back to sponsors —
 * store what was decided, not what it evaluates to (doc 03, grants table).
 */
enum GrantBenefit: string implements HasColor, HasLabel
{
    case Free = 'free';
    case CustomPrice = 'custom_price';
    case PercentOff = 'percent_off';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => __('Free registration'),
            self::CustomPrice => __('Custom price'),
            self::PercentOff => __('Percentage off'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Free => 'success',
            self::CustomPrice => 'info',
            self::PercentOff => 'info',
        };
    }
}
