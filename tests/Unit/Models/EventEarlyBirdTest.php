<?php

use App\Enums\PaymentMethod;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 * Early bird pricing (docs/29): $275 through December 31, 2026, then the regular $325.
 * December 31 is a price increase, not a deadline; registration closes on its own date.
 *
 * The cutoff is January 1 00:00 Chattanooga time. Every boundary test below
 * names both sides of it, so a test that only ever sees one fee cannot pass
 * by accident.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cutoff = Carbon::parse('2027-01-01 00:00', 'America/New_York');
    $this->fair = Event::factory()->withEarlyBird($this->cutoff)->create();
    $this->organization = Organization::factory()->create();
});

describe('the clock', function () {
    it('runs the app on Chattanooga time', function () {
        expect(config('app.timezone'))->toBe('America/New_York');
    });

    it('ends the early fee at local midnight, not 7 p.m.', function () {
        // 7:30 p.m. on December 31 in Chattanooga is already January 1 in UTC.
        // Under a UTC app clock this would have been the regular price.
        $evening = Carbon::parse('2026-12-31 19:30', 'America/New_York');

        expect($this->fair->listPriceAt($evening))->toBe(27500);
    });
});

describe('listPriceAt', function () {
    it('charges the early fee up to the last minute of December 31', function () {
        expect($this->fair->listPriceAt(Carbon::parse('2026-12-31 23:59', 'America/New_York')))->toBe(27500);
    });

    it('charges the regular price from midnight on January 1', function () {
        expect($this->fair->listPriceAt($this->cutoff))->toBe(32500)
            ->and($this->fair->listPriceAt(Carbon::parse('2027-03-01 12:00', 'America/New_York')))->toBe(32500);
    });

    it('keeps one price all season for a fair with no regular price set', function () {
        $single = Event::factory()->priced(21500)->create();

        expect($single->hasRegularPrice())->toBeFalse()
            ->and($single->listPriceAt($this->cutoff->copy()->addMonth()))->toBe(21500);
    });

    it('ignores a regular price with no early bird end, and an end with no price', function () {
        $noDate = Event::factory()->priced(27500)->create(['regular_price_cents' => 32500]);
        $noFee = Event::factory()->priced(27500)->create(['regular_price_starts_at' => $this->cutoff]);

        expect($noDate->listPriceAt($this->cutoff->copy()->addDay()))->toBe(27500)
            ->and($noFee->listPriceAt($this->cutoff->copy()->addDay()))->toBe(27500);
    });
});

describe('priceFor', function () {
    it('applies a percent-off grant to whichever fee is current', function () {
        Grant::factory()->percentOff(20)->for($this->fair)->for($this->organization)->create();

        expect($this->fair->priceFor($this->organization, $this->cutoff->copy()->subDay()))->toBe(22000)
            ->and($this->fair->priceFor($this->organization, $this->cutoff))->toBe(26000);
    });

    it('holds a custom-price grant at its figure on both sides of the cutoff', function () {
        Grant::factory()->customPrice(10000)->for($this->fair)->for($this->organization)->create();

        expect($this->fair->priceFor($this->organization, $this->cutoff->copy()->subDay()))->toBe(10000)
            ->and($this->fair->priceFor($this->organization, $this->cutoff))->toBe(10000);
    });
});

describe('the registration snapshot', function () {
    beforeEach(function () {
        $this->fair->update([
            'is_published' => true,
            'registration_opens_at' => Carbon::parse('2026-12-01 09:00', 'America/New_York'),
            'registration_closes_at' => Carbon::parse('2027-03-23 18:30', 'America/New_York'),
        ]);
        $this->rep = User::factory()->rep($this->organization)->create();
    });

    it('locks in the early fee for a registration made on December 31', function () {
        // Paying by check in January must not raise what was owed in December.
        $this->travelTo(Carbon::parse('2026-12-31 22:00', 'America/New_York'));

        $registration = app(RegistrationService::class)
            ->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check);

        $this->travelTo($this->cutoff->copy()->addWeek());

        expect($registration->refresh()->price_cents)->toBe(27500);
    });

    it('charges the regular price for a registration made on January 1', function () {
        $this->travelTo($this->cutoff->copy()->addMinutes(5));

        $registration = app(RegistrationService::class)
            ->create($this->fair, $this->organization, $this->rep, PaymentMethod::Check);

        expect($registration->price_cents)->toBe(32500);
    });
});

describe('feeSummary', function () {
    it('names both fees and the last early day while the early fee lasts', function () {
        expect($this->fair->feeSummary(Carbon::parse('2026-12-15 12:00', 'America/New_York')))
            ->toBe('$275.00 early bird through December 31, then $325.00');
    });

    it('names only the regular price once the early bird ends', function () {
        expect($this->fair->feeSummary($this->cutoff))->toBe('$325.00');
    });

    it('names the one fee of a fair without early bird pricing', function () {
        expect(Event::factory()->priced(21500)->create()->feeSummary())->toBe('$215.00');
    });
});

describe('vocabulary (owner, 2026-11-11)', function () {
    it('calls the price after December 31 the regular price, in the schema too', function () {
        expect(Schema::hasColumns('events', ['regular_price_cents', 'regular_price_starts_at']))->toBeTrue()
            ->and(Schema::hasColumn('events', 'late_price_cents'))->toBeFalse()
            ->and(Schema::hasColumn('events', 'late_price_starts_at'))->toBeFalse();
    });

    it('never calls the price increase a late fee or a deadline', function () {
        $summary = $this->fair->feeSummary(Carbon::parse('2026-12-15 12:00', 'America/New_York'));

        expect($summary)->toContain('early bird')
            ->not->toContain('late')
            ->not->toContain('deadline');
    });
});
