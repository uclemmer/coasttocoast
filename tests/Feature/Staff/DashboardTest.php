<?php

use App\Livewire\Staff\Dashboard;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;

/*
 * The staff landing page (docs/13), replacing the admin panel's two dashboard
 * widgets. Ported from DashboardWidgetsTest.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

it('says so plainly when no fair is active', function () {
    expect(livewire(Dashboard::class)->instance()->numbers())->toBeNull();
});

it('counts confirmed schools and what they have paid', function () {
    $fair = Fair::factory()->published()->priced(21500)->create();
    Registration::factory()->count(2)->forEvent($fair)->create(['price_cents' => 21500]);

    $numbers = livewire(Dashboard::class)->instance()->numbers();

    expect($numbers['confirmed'])->toBe(2)
        ->and($numbers['collected'])->toBe(43000);
});

/*
 * The money comes from registrations, not the payments table, so "collected"
 * agrees with the price each school was quoted. The two answer different
 * questions and would disagree by whatever is in flight.
 */
it('separates money awaited from money collected, and names the checks', function () {
    $fair = Fair::factory()->published()->priced(21500)->create();
    Registration::factory()->forEvent($fair)->create(['price_cents' => 21500]);
    Registration::factory()->pendingCheck()->forEvent($fair)->create(['price_cents' => 21500]);

    $numbers = livewire(Dashboard::class)->instance()->numbers();

    expect($numbers['collected'])->toBe(21500)
        ->and($numbers['awaited'])->toBe(21500)
        ->and($numbers['awaitingChecks'])->toBe(1);
});

it('shows the ten most recent registrations for the active fair only', function () {
    $fair = Fair::factory()->published()->create();
    $other = Fair::factory()->create();

    Registration::factory()->count(12)->forEvent($fair)->create();
    $elsewhere = Registration::factory()->forEvent($other)->create();

    $recent = livewire(Dashboard::class)->instance()->recent();

    expect($recent)->toHaveCount(10)
        ->and($recent->pluck('id')->all())->not->toContain($elsewhere->id);
});

it('surfaces grant applications waiting on a decision', function () {
    Fair::factory()->published()->create();
    Grant::factory()->count(2)->for(Fair::factory()->create())->for(Organization::factory())->create();

    expect(livewire(Dashboard::class)->instance()->pendingGrants())->toBe(2);
});

it('keeps a user who is not staff out', function () {
    $this->actingAs(User::factory()->rep()->create());

    livewire(Dashboard::class)->assertForbidden();
});
