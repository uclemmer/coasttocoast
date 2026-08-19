<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a representative stands with the organization they claim to speak for.
 *
 * Set by the signup flow (D9): creating a brand-new organization makes the rep
 * `Active` immediately, claiming an existing one makes them `Pending` until a
 * coordinator approves. `Retired` reps keep their account and their history but
 * lose every org right, and campaigns never mail them (doc 07 §2 rule 1).
 *
 * Coordinators have no organization and therefore a null membership status.
 */
enum MembershipStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case Retired = 'retired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting approval'),
            self::Active => __('Active'),
            self::Retired => __('Retired'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Retired => 'gray',
        };
    }

    /**
     * Whether this status carries the rights that belong to the organization:
     * registering, applying for grants, and editing the org profile.
     *
     * One method rather than `=== Active` scattered through services and
     * policies, so a future fourth case has exactly one place to declare itself.
     */
    public function actsForOrganization(): bool
    {
        return $this === self::Active;
    }
}
