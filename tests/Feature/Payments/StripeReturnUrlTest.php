<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\Payments\StripeCheckoutService;
use Illuminate\Support\Facades\Route;
use Stripe\StripeClient;

/*
 * Where Stripe sends the rep back to.
 *
 * This file exists because of a bug it would have caught. `successUrl()` and
 * `cancelUrl()` were built from `App\Filament\Rep\Resources\RegistrationResource`,
 * and commit 582bb13 deleted that class when the Filament rep panel was rebuilt
 * as the Livewire screens at /portal. The import went stale, the live card
 * payment path gained a `Class not found`, and the suite stayed at 670 green —
 * because the only tests touching StripeCheckoutService exercise guard clauses
 * that throw before either method is reached, and every other payment test
 * binds FakePaymentGateway.
 *
 * So the lesson is narrower than "add a test": a method no test ever CALLS can
 * be deleted out from under, and being green says nothing about it. These two
 * are called directly.
 *
 * They are protected, deliberately — nothing outside the service should be
 * constructing Stripe return URLs — so the test reaches them through a subclass
 * rather than by making the real methods public for testing's sake.
 */

/** Exposes the two protected URL builders, and nothing else. */
class ExposedCheckoutService extends StripeCheckoutService
{
    public function publicSuccessUrl(Registration $registration): string
    {
        return $this->successUrl($registration);
    }

    public function publicCancelUrl(): string
    {
        return $this->cancelUrl();
    }
}

beforeEach(function (): void {
    $this->service = new ExposedCheckoutService(new StripeClient('sk_test_123'));
});

it('sends a paying rep back to their own registration', function () {
    $registration = Registration::factory()
        ->forEvent(Event::factory()->create())
        ->forOrganization(Organization::factory()->create())
        ->create();

    $url = $this->service->publicSuccessUrl($registration);

    expect($url)
        // Absolute: Stripe rejects a relative return URL outright.
        ->toStartWith('http')
        ->toContain('/portal/registrations/'.$registration->getKey());
});

it('sends an abandoning rep back to their registration list', function () {
    expect($this->service->publicCancelUrl())
        ->toStartWith('http')
        ->toEndWith('/portal/registrations');
});

/*
 * The paths above are asserted literally rather than through route(), which
 * would be tautological. This test is the other half: the names those paths
 * come from still exist. If a route is renamed, one of these two fails and
 * says which.
 */
it('builds both from routes that exist', function () {
    expect(Route::has('portal.registrations.show'))->toBeTrue()
        ->and(Route::has('portal.registrations'))->toBeTrue();

    // And nothing in the service reaches for the retired Filament panel.
    $source = file_get_contents(app_path('Services/Payments/StripeCheckoutService.php'));
    $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);

    expect($code)->not->toContain('Filament');
});
