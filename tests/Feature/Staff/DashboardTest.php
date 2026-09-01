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

/*
 * The two pieces the design handoff's admin dashboard added (docs/16): the
 * registrations-per-week chart and the countdown card.
 */
it('buckets registrations into twelve weeks, including the empty ones', function () {
    $fair = Fair::factory()->published()->create();

    // Two this week, one nine weeks back, one twenty weeks back — outside the
    // window and therefore not plotted at all.
    Registration::factory()->count(2)->forEvent($fair)->create();
    Registration::factory()->forEvent($fair)->create(['created_at' => now()->subWeeks(9)]);
    Registration::factory()->forEvent($fair)->create(['created_at' => now()->subWeeks(20)]);

    $weeks = livewire(Dashboard::class)->instance()->weeklyRegistrations();

    // A week nobody registered in has to be a zero, not a gap: the shape of
    // the run-up is the thing being read, and a chart that silently drops its
    // quiet weeks draws a busier season than happened.
    expect($weeks['labels'])->toHaveCount(12)
        ->and($weeks['values'])->toHaveCount(12)
        ->and(array_sum($weeks['values']))->toBe(3)
        ->and($weeks['values'][11])->toBe(2)
        ->and($weeks['values'][2])->toBe(1);
});

it('counts registrations for the active fair only', function () {
    $fair = Fair::factory()->published()->create();
    Registration::factory()->forEvent($fair)->create();
    Registration::factory()->forEvent(Fair::factory()->create())->create();

    expect(array_sum(livewire(Dashboard::class)->instance()->weeklyRegistrations()['values']))->toBe(1);
});

it('counts whole days to the fair, from midnight rather than from now', function () {
    // The coordinator reads this as "how many days do I have". Diffing two
    // timestamps answers something else: a fair opening at 18:30 tomorrow
    // would be one day away all morning and zero days away all afternoon.
    $fair = Fair::factory()->published()->create([
        'starts_at' => now()->addDays(10)->setTime(18, 30),
        'ends_at' => now()->addDays(10)->setTime(20, 0),
    ]);

    expect($fair->refresh())->not->toBeNull()
        ->and(livewire(Dashboard::class)->instance()->daysUntilFair())->toBe(10);
});

it('stops counting once the fair is behind us', function () {
    Fair::factory()->past()->create();

    expect(livewire(Dashboard::class)->instance()->daysUntilFair())->toBeNull();
});

it('renders the chart and the countdown card', function () {
    // Registration is not rendering: the computed properties above can each be
    // right while the page 500s on the way to using them.
    $fair = Fair::factory()->published()->create([
        'starts_at' => now()->addDays(10)->setTime(18, 30),
        'ends_at' => now()->addDays(10)->setTime(20, 0),
    ]);
    Registration::factory()->forEvent($fair)->create();

    livewire(Dashboard::class)
        ->assertOk()
        ->assertSee('Registrations per week')
        ->assertSee('Event countdown')
        ->assertSee('10 days')
        ->assertSee(route('staff.events.edit', $fair));
});
