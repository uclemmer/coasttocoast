<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('casts', function () {
    it('casts status, method, money, the roster flag and timestamps', function () {
        $registration = Registration::factory()->create();

        expect($registration->status)->toBe(RegistrationStatus::Confirmed)
            ->and($registration->payment_method)->toBe(PaymentMethod::Stripe)
            ->and($registration->price_cents)->toBeInt()
            ->and($registration->show_on_roster)->toBeTrue()
            ->and($registration->confirmed_at)->toBeInstanceOf(Carbon::class)
            ->and($registration->cancelled_at)->toBeNull();
    });

    it('keeps the payment method null on a free registration', function () {
        // Nothing was charged, so there is no method to record. A `free`
        // payment method would invite a payment row for money never moved.
        $registration = Registration::factory()->free()->create();

        expect($registration->payment_method)->toBeNull()
            ->and($registration->isFree())->toBeTrue()
            ->and($registration->price_cents)->toBe(0);
    });
});

describe('relationships', function () {
    it('resolves the event, organization, rep, grant and payments', function () {
        $event = Event::factory()->create();
        $organization = Organization::factory()->create();
        $user = User::factory()->rep($organization)->create();
        $grant = Grant::factory()->free()->for($event)->for($organization)->create();

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'grant_id' => $grant->id,
        ]);
        Payment::factory()->count(2)->for($registration)->create();

        expect($registration->event->is($event))->toBeTrue()
            ->and($registration->organization->is($organization))->toBeTrue()
            ->and($registration->user->is($user))->toBeTrue()
            ->and($registration->grant->is($grant))->toBeTrue()
            ->and($registration->payments()->count())->toBe(2);
    });

    it('has no rep on a manual entry but keeps the contact snapshot', function () {
        $registration = Registration::factory()->manualEntry()->create(['rep_email' => 'dean@example.edu']);

        expect($registration->user)->toBeNull()
            ->and($registration->rep_email)->toBe('dean@example.edu');
    });

    it('finds the payment that actually settled it', function () {
        $registration = Registration::factory()->create();
        Payment::factory()->for($registration)->failed()->create();
        $succeeded = Payment::factory()->for($registration)->create(['status' => PaymentStatus::Succeeded]);

        expect($registration->successfulPayment()?->id)->toBe($succeeded->id);
    });

    it('has no successful payment when none succeeded', function () {
        $registration = Registration::factory()->pendingStripe()->create();
        Payment::factory()->for($registration)->pending()->create();

        expect($registration->successfulPayment())->toBeNull();
    });
});

describe('seat occupancy', function () {
    it('holds a seat while confirmed or awaiting payment', function (string $state, bool $occupies) {
        $registration = Registration::factory()->{$state}()->create();

        expect($registration->occupiesASeat())->toBe($occupies);
    })->with([
        'confirmed' => ['free', true],
        'awaiting a card payment' => ['pendingStripe', true],
        'awaiting a check' => ['pendingCheck', true],
        'cancelled' => ['cancelled', false],
        'refunded' => ['refunded', false],
    ]);

    it('scopes to occupying registrations', function () {
        Registration::factory()->create();
        Registration::factory()->pendingCheck()->create();
        Registration::factory()->cancelled()->create();
        Registration::factory()->refunded()->create();

        expect(Registration::query()->occupying()->count())->toBe(2);
    });
});

describe('roster scope', function () {
    it('shows only confirmed registrations the coordinator has not hidden', function () {
        // R1.3 and R3.4. The roster is a promise the organization will be there, so
        // an unpaid registration has no business on it.
        $visible = Registration::factory()->create();
        Registration::factory()->hiddenFromRoster()->create();
        Registration::factory()->pendingCheck()->create();
        Registration::factory()->cancelled()->create();
        Registration::factory()->refunded()->create();

        expect(Registration::query()->onRoster()->pluck('id')->all())->toBe([$visible->id]);
    });

    it('scopes to confirmed regardless of the roster flag', function () {
        Registration::factory()->create();
        Registration::factory()->hiddenFromRoster()->create();
        Registration::factory()->pendingCheck()->create();

        expect(Registration::query()->confirmed()->count())->toBe(2);
    });
});
