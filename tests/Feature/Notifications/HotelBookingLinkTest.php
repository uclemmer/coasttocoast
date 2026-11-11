<?php

use App\Enums\PaymentMethod;
use App\Models\Event as Fair;
use App\Models\Organization;
use App\Models\Registration;
use App\Notifications\PaymentReceipt;
use App\Notifications\RegistrationCheckInstructions;
use Illuminate\Notifications\AnonymousNotifiable;

/*
 * The hotel's group-rate link in both confirmation emails (docs/29), so a rep
 * gets it whichever way they pay. Rendered through the real views.
 */

const BOOKING_URL = 'https://www.marriott.com/event-reservations/reservation-link.mi?id=1793027880667&key=GRP';

function renderedFor(object $notification): string
{
    return (string) $notification->toMail(new AnonymousNotifiable)->render();
}

beforeEach(function () {
    $this->fair = Fair::factory()->published()->create(['hotel_booking_url' => BOOKING_URL]);
    $this->registration = Registration::factory()->forEvent($this->fair)
        ->forOrganization(Organization::factory()->create())
        ->create(['payment_method' => PaymentMethod::Check]);
});

it('puts the booking link in the payment receipt', function () {
    expect(renderedFor(new PaymentReceipt($this->registration)))
        ->toContain(e(BOOKING_URL))
        ->toContain('Book your hotel room');
});

it('puts the booking link in the pay-by-check instructions', function () {
    expect(renderedFor(new RegistrationCheckInstructions($this->registration)))
        ->toContain(e(BOOKING_URL))
        ->toContain('Book your hotel room');
});

it('leaves the section out when the fair has no link', function () {
    $this->fair->update(['hotel_booking_url' => null]);

    expect(renderedFor(new PaymentReceipt($this->registration->refresh())))
        ->not->toContain('Book your hotel room')
        ->and(renderedFor(new RegistrationCheckInstructions($this->registration)))
        ->not->toContain('Book your hotel room');
});
