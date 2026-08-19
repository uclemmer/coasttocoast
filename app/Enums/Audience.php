<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Who a campaign goes to (doc 07 §2).
 *
 * Every case names a set of **organizations** relative to a reference event
 * (default: the active event). `AudienceBuilder` turns that set into people —
 * each qualifying organization's *active* reps, or a single generic recipient
 * at its `admissions_email` when it has none. `InterestList` is the one case
 * with no organizations behind it at all.
 *
 * The descriptions are shown verbatim as Filament helper text, because
 * "lapsed" means nothing to a coordinator until it is spelled out.
 */
enum Audience: string implements HasDescription, HasLabel
{
    case ThisEventConfirmed = 'this_event_confirmed';
    case ThisEventPendingCheck = 'this_event_pending_check';
    case ThisEventAll = 'this_event_all';
    case LastEvent = 'last_event';
    case LapsedLastEvent = 'lapsed_last_event';
    case AnyPreviousEvent = 'any_previous_event';
    case LapsedAnyPrevious = 'lapsed_any_previous';
    case InterestList = 'interest_list';

    public function getLabel(): string
    {
        return match ($this) {
            self::ThisEventConfirmed => __('This fair — confirmed'),
            self::ThisEventPendingCheck => __('This fair — awaiting a check'),
            self::ThisEventAll => __('This fair — everyone registered'),
            self::LastEvent => __('Last fair — everyone registered'),
            self::LapsedLastEvent => __('Last fair, not yet this one'),
            self::AnyPreviousEvent => __('Any past fair'),
            self::LapsedAnyPrevious => __('Any past fair, not yet this one'),
            self::InterestList => __('Interest list — "notify me"'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::ThisEventConfirmed => __('Schools with a confirmed registration for this fair.'),
            self::ThisEventPendingCheck => __('Schools that chose to pay by check and whose check has not arrived.'),
            self::ThisEventAll => __('Schools with any live registration for this fair — confirmed or awaiting payment.'),
            self::LastEvent => __('Schools that registered for the most recent past fair.'),
            self::LapsedLastEvent => __('Schools that registered for the most recent past fair but have not registered for this one.'),
            self::AnyPreviousEvent => __('Schools that registered for any past fair.'),
            self::LapsedAnyPrevious => __('The win-back list: schools that have attended before but have not registered for this one.'),
            self::InterestList => __('People who asked to be told when registration opens. Email only — no school attached.'),
        };
    }

    /**
     * Cases whose membership is defined by subtracting everyone already
     * registered for the reference event. Kept here so the builder and the
     * composer's helper text cannot disagree about which cases are "lapsed".
     *
     * @return array<int, self>
     */
    public static function lapsed(): array
    {
        return [self::LapsedLastEvent, self::LapsedAnyPrevious];
    }

    /**
     * Whether this audience resolves to raw email addresses rather than to
     * organizations and their reps. Only the interest list does — it exists
     * precisely for people who have no school record yet.
     */
    public function isEmailOnly(): bool
    {
        return $this === self::InterestList;
    }
}
