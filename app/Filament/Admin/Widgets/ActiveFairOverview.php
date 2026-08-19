<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The three numbers the coordinator actually wants on a Tuesday (R3.8), all
 * for the active fair.
 *
 * "Revenue collected" counts confirmed registrations' snapshot prices rather
 * than summing the payments table, and the difference is deliberate: a free
 * registration has no payment row, and counting payments would quietly report
 * a grant-heavy year as a bad one. What is owed is the awaiting-payment set,
 * which is money in the post, not money lost.
 */
class ActiveFairOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $fair = Event::active();

        if (! $fair instanceof Event) {
            return [
                Stat::make(__('Active fair'), __('None'))
                    ->description(__('Publish a fair to see its numbers here.'))
                    ->color('gray'),
            ];
        }

        $confirmed = $fair->registrations()->confirmed();
        $pending = $fair->registrations()->where('status', RegistrationStatus::PendingPayment);

        $confirmedCount = (clone $confirmed)->count();
        $collected = (clone $confirmed)->sum('price_cents');
        $awaited = (clone $pending)->sum('price_cents');
        $awaitingChecks = (clone $pending)->where('payment_method', PaymentMethod::Check)->count();

        return [
            Stat::make(__('Confirmed schools'), (string) $confirmedCount)
                ->description($fair->capacity === null
                    ? $fair->name
                    : __(':left of :capacity places left', [
                        'left' => $fair->remainingCapacity(),
                        'capacity' => $fair->capacity,
                    ]))
                ->color($fair->isFull() ? 'warning' : 'success'),

            Stat::make(__('Collected'), Money::format((int) $collected))
                ->description(__('Confirmed registrations, at the price each school was quoted'))
                ->color('success'),

            Stat::make(__('Awaiting payment'), Money::format((int) $awaited))
                ->description(__(':checks of these are checks in the post', ['checks' => $awaitingChecks]))
                ->color($awaited > 0 ? 'warning' : 'gray'),
        ];
    }
}
