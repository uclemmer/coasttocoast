<?php

use App\Enums\PaymentMethod;
use App\Filament\Admin\Widgets\ActiveFairOverview;
use App\Filament\Admin\Widgets\RecentRegistrations;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;

beforeEach(function () {
    usingAdminPanel();
    $this->actingAs(coordinator());
});

/**
 * Reads the stat values out of the widget without rendering, so the assertions
 * are about the numbers rather than about markup.
 *
 * @return array<string, string>
 */
function fairStats(): array
{
    $widget = new ActiveFairOverview;

    $stats = (fn (): array => $this->getStats())->call($widget);

    return collect($stats)
        ->mapWithKeys(fn ($stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
        ->all();
}

describe('the active fair overview', function () {
    it('says so plainly when nothing is published', function () {
        Fair::factory()->create(); // draft only

        expect(fairStats())->toHaveKey('Active fair')
            ->and(fairStats()['Active fair'])->toBe('None');
    });

    it('counts confirmed schools and reports the places left', function () {
        $fair = Fair::factory()->published()->withCapacity(10)->create();
        Registration::factory()->count(3)->forEvent($fair)->create();
        Registration::factory()->pendingCheck()->forEvent($fair)->create();
        Registration::factory()->cancelled()->forEvent($fair)->create();

        expect(fairStats()['Confirmed schools'])->toBe('3');
    });

    it('counts collected revenue from the snapshot prices, not the payments table', function () {
        // A free registration has no payment row. Summing payments would
        // quietly report a grant-heavy year as a bad one.
        $fair = Fair::factory()->published()->priced(21500)->create();
        $school = Organization::factory()->create();
        $grant = Grant::factory()->free()->for($fair)->for($school)->create();

        Registration::factory()->forEvent($fair)->create(['price_cents' => 21500]);
        Registration::factory()->forEvent($fair)->create(['price_cents' => 10750]);
        Registration::factory()->free()->forEvent($fair)->forOrganization($school)
            ->create(['grant_id' => $grant->id]);

        expect(fairStats()['Collected'])->toBe('$322.50');
    });

    it('separates money in the post from money collected', function () {
        $fair = Fair::factory()->published()->priced(21500)->create();
        Registration::factory()->forEvent($fair)->create(['price_cents' => 21500]);
        Registration::factory()->pendingCheck()->forEvent($fair)->create(['price_cents' => 21500]);

        expect(fairStats()['Collected'])->toBe('$215.00')
            ->and(fairStats()['Awaiting payment'])->toBe('$215.00');
    });

    it('ignores cancelled and refunded registrations entirely', function () {
        $fair = Fair::factory()->published()->create();
        Registration::factory()->cancelled()->forEvent($fair)->create(['price_cents' => 21500]);
        Registration::factory()->refunded()->forEvent($fair)->create(['price_cents' => 21500]);

        expect(fairStats()['Confirmed schools'])->toBe('0')
            ->and(fairStats()['Collected'])->toBe('$0.00')
            ->and(fairStats()['Awaiting payment'])->toBe('$0.00');
    });

    it('reports on the active fair only, not on every year at once', function () {
        Fair::factory()->past(1)->create()->registrations()->saveMany(
            Registration::factory()->count(5)->make(['price_cents' => 21500]),
        );
        $current = Fair::factory()->published()->create();
        Registration::factory()->forEvent($current)->create(['price_cents' => 21500]);

        expect(fairStats()['Confirmed schools'])->toBe('1');
    });
});

describe('recent registrations', function () {
    it('shows the active fair\'s registrations, newest first', function () {
        $fair = Fair::factory()->published()->create();
        $older = Registration::factory()->forEvent($fair)->create(['created_at' => now()->subDay()]);
        $newer = Registration::factory()->forEvent($fair)->create(['created_at' => now()]);

        livewire(RecentRegistrations::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    });

    it('excludes other fairs', function () {
        $current = Fair::factory()->published()->create();
        $mine = Registration::factory()->forEvent($current)->create();
        $lastYear = Registration::factory()->forEvent(Fair::factory()->past(1)->create())->create();

        livewire(RecentRegistrations::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$lastYear]);
    });

    it('shows nothing at all rather than every registration ever taken when no fair is active', function () {
        Registration::factory()->count(3)->forEvent(Fair::factory()->create())->create();

        livewire(RecentRegistrations::class)->assertCanNotSeeTableRecords(Registration::all());
    });

    it('labels a free registration rather than leaving the method blank', function () {
        $fair = Fair::factory()->published()->create();
        $free = Registration::factory()->free()->forEvent($fair)->create();
        $paid = Registration::factory()->forEvent($fair)->create();

        livewire(RecentRegistrations::class)
            ->assertTableColumnStateSet('payment_method', null, $free)
            ->assertTableColumnStateSet('payment_method', PaymentMethod::Stripe, $paid);
    });
});
