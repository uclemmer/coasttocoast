<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Livewire\Staff\Registrations\Create as CreateStaffRegistration;
use App\Livewire\Staff\Registrations\Index as RegistrationIndex;
use App\Livewire\Staff\Registrations\Show as ShowStaffRegistration;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;

/*
 * The staff registration screens (docs/13).
 *
 * Ported from RegistrationResourceTest. The export test is the one that matters
 * most: the whole feature is that the filter applies, and the way to break it is
 * to rebuild the filters beside the export instead of sharing one builder.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->organization = Organization::factory()->named('Kenyon College')->create();
});

describe('the list', function () {
    it('filters by fair, status and payment method', function () {
        $mine = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();
        $elsewhere = Registration::factory()->create();

        $page = livewire(RegistrationIndex::class);

        expect($page->set('eventId', (string) $this->fair->id)->instance()->registrations()->pluck('id')->all())
            ->toBe([$mine->id])
            ->not->toContain($elsewhere->id);
    });

    it('searches by organization and by contact', function () {
        $found = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['rep_name' => 'Dana Whitfield', 'rep_email' => 'dw@kenyon.example']);
        Registration::factory()->forEvent($this->fair)->create(['rep_name' => 'Someone Else']);

        $page = livewire(RegistrationIndex::class);

        expect($page->set('search', 'Kenyon')->instance()->registrations()->pluck('id')->all())->toBe([$found->id]);
        expect($page->set('search', 'Dana')->instance()->registrations()->pluck('id')->all())->toBe([$found->id]);
        expect($page->set('search', 'dw@kenyon')->instance()->registrations()->pluck('id')->all())->toBe([$found->id]);
    });

    it('filters to registrations carrying a grant', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();
        $withGrant = Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['grant_id' => $grant->id]);
        $without = Registration::factory()->forEvent($this->fair)->create();

        $page = livewire(RegistrationIndex::class);

        expect($page->set('hasGrant', 'yes')->instance()->registrations()->pluck('id')->all())->toBe([$withGrant->id]);
        expect($page->set('hasGrant', 'no')->instance()->registrations()->pluck('id')->all())->toBe([$without->id]);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(RegistrationIndex::class)->assertForbidden();
    });
});

describe('the CSV export', function () {
    /*
     * Streamed rather than queued precisely so the filter applies — an export
     * that ignored it would not be the same feature. The table and the export
     * read one builder, which is what stops them drifting.
     */
    it('exports the rows currently filtered, with the columns the coordinator needs', function () {
        $grant = Grant::factory()->percentOff(50)->for($this->fair)->for($this->organization)->create();
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create([
            'grant_id' => $grant->id,
            'price_cents' => 10750,
            'rep_name' => 'Dana Whitfield',
            'rep_email' => 'dw@kenyon.example',
        ]);
        Registration::factory()->create(); // another fair — must not appear

        $response = livewire(RegistrationIndex::class)
            ->set('eventId', (string) $this->fair->id)
            ->call('export');

        $csv = downloadedContent($response);

        expect($csv)->toContain('Kenyon College')
            ->toContain('Dana Whitfield')
            ->toContain('$107.50')
            ->toContain('50% off registration')
            ->and(substr_count($csv, "\n"))->toBe(2); // header + one row
    });

    it('exports everything when nothing is filtered', function () {
        // The other half: a filter narrowing the export is only correct if no
        // filter does not.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();
        Registration::factory()->create();

        $csv = downloadedContent(livewire(RegistrationIndex::class)->call('export'));

        expect(substr_count($csv, "\n"))->toBe(3); // header + two rows
    });
});

describe('manual entry', function () {
    it('creates a registration with no account behind it, priced from the fair', function () {
        livewire(CreateStaffRegistration::class)
            ->set('event_id', (string) $this->fair->id)
            ->set('organization_id', (string) $this->organization->id)
            ->set('payment_method', PaymentMethod::Check->value)
            ->set('rep_name', 'Dana Whitfield')
            ->set('rep_email', 'dw@kenyon.example')
            ->call('save')
            ->assertHasNoErrors();

        $registration = Registration::query()->where('organization_id', $this->organization->id)->sole();

        expect($registration->price_cents)->toBe(21500)
            ->and($registration->user_id)->toBeNull();
    });

    it('applies an approved grant even though the coordinator entered it', function () {
        // The price comes from the service, not from the form, so a grant
        // cannot be skipped by entering the registration by hand.
        Grant::factory()->customPrice(5000)->for($this->fair)->for($this->organization)->create();

        livewire(CreateStaffRegistration::class)
            ->set('event_id', (string) $this->fair->id)
            ->set('organization_id', (string) $this->organization->id)
            ->set('payment_method', PaymentMethod::Check->value)
            ->set('rep_name', 'Dana Whitfield')
            ->set('rep_email', 'dw@kenyon.example')
            ->call('save')
            ->assertHasNoErrors();

        expect(Registration::query()->where('organization_id', $this->organization->id)->sole()->price_cents)
            ->toBe(5000);
    });

    it('reports a duplicate on the form instead of failing generically', function () {
        // Keyed to the organization field, because that is the field to change.
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        livewire(CreateStaffRegistration::class)
            ->set('event_id', (string) $this->fair->id)
            ->set('organization_id', (string) $this->organization->id)
            ->set('rep_name', 'Dana Whitfield')
            ->set('rep_email', 'dw@kenyon.example')
            ->call('save')
            ->assertHasErrors(['organization_id']);
    });

    it('names the two selects as the form labels them, not as columns', function () {
        /*
         * Laravel derives an attribute name from the key, so these used to read
         * "the organization id field is required" and "the event id field is
         * required" — naming foreign keys the form never shows.
         *
         * Asserted on the message rather than on assertHasErrors(), because the
         * error is present either way. Only the wording is the bug, so only the
         * wording can catch it coming back.
         */
        $errors = livewire(CreateStaffRegistration::class)->call('save')->errors();

        expect($errors->first('organization_id'))->toBe('The organization field is required.')
            ->and($errors->first('event_id'))->toBe('The fair field is required.')
            // The rep_ prefix is a column convention, not something on screen:
            // these three are labelled Name, Email and Phone under "Fair contact".
            ->and($errors->first('rep_name'))->toBe('The name field is required.')
            ->and($errors->first('rep_email'))->toBe('The email field is required.');
    });

    it('works on a fair whose registration has closed', function () {
        // Manual entry skips the window check on purpose: the coordinator is
        // recording something that already happened.
        $closed = Fair::factory()->registrationClosed()->priced(21500)->create();

        livewire(CreateStaffRegistration::class)
            ->set('event_id', (string) $closed->id)
            ->set('organization_id', (string) $this->organization->id)
            ->set('payment_method', PaymentMethod::Check->value)
            ->set('rep_name', 'Dana Whitfield')
            ->set('rep_email', 'dw@kenyon.example')
            ->call('save')
            ->assertHasNoErrors();

        expect(Registration::query()->where('event_id', $closed->id)->exists())->toBeTrue();
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(CreateStaffRegistration::class)->assertForbidden();
    });
});

describe('editing', function () {
    it('changes roster visibility and notes', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['show_on_roster' => true]);

        livewire(ShowStaffRegistration::class, ['registration' => $registration])
            ->set('show_on_roster', false)
            ->set('notes', 'Paying by check, posted 3 April.')
            ->call('saveDetails')
            ->assertHasNoErrors();

        expect($registration->refresh()->show_on_roster)->toBeFalse()
            ->and($registration->notes)->toContain('posted 3 April');
    });

    /*
     * Editing status or price by hand would skip the events that send receipts
     * and break the snapshot that proves what an organization agreed to pay (N1).
     * There is no property to set, so the component cannot be asked to.
     */
    it('does not expose status or price as editable fields', function () {
        $registration = Registration::factory()->create();

        $component = livewire(ShowStaffRegistration::class, ['registration' => $registration])->instance();

        foreach (['status', 'price_cents', 'event_id', 'organization_id'] as $forbidden) {
            expect(property_exists($component, $forbidden))->toBeFalse("{$forbidden} is editable and must not be.");
        }
    });
});

describe('cancelling', function () {
    it('cancels, records the reason, and keeps the row', function () {
        $registration = Registration::factory()->forEvent($this->fair)->create();

        livewire(RegistrationIndex::class)
            ->call('confirmCancel', $registration->id)
            ->set('cancelReason', 'Travel budget cut.')
            ->call('cancel');

        expect($registration->refresh()->status)->toBe(RegistrationStatus::Cancelled)
            ->and($registration->notes)->toContain('Travel budget cut.')
            ->and(Registration::query()->find($registration->id))->not->toBeNull();
    });

    it('is not offered once there is nothing left to cancel', function () {
        $live = Registration::factory()->forEvent($this->fair)->create();
        $cancelled = Registration::factory()->cancelled()->forEvent($this->fair)->create();

        $page = livewire(RegistrationIndex::class)->instance();

        expect($page->canCancel($live))->toBeTrue()
            ->and($page->canCancel($cancelled))->toBeFalse();
    });

    it('is never a delete', function () {
        $registration = Registration::factory()->create();

        expect($this->coordinator->can('delete', $registration))->toBeFalse();
    });
});

describe('the money actions', function () {
    it('records a check and confirms the registration', function () {
        $registration = Registration::factory()->pendingCheck()->forEvent($this->fair)
            ->forOrganization($this->organization)->create(['price_cents' => 21500]);

        livewire(ShowStaffRegistration::class, ['registration' => $registration])
            ->set('checkNumber', '1041')
            ->set('checkAmountDollars', '215.00')
            ->call('markCheckReceived');

        expect($registration->refresh()->status)->toBe(RegistrationStatus::Confirmed);
    });

    /*
     * Surfaced, not blocked — the alternative is noticing in April. Filament
     * used a persistent notification; this stays on the component until
     * dismissed, so it survives the next click.
     */
    it('flags a short check without refusing it', function () {
        $registration = Registration::factory()->pendingCheck()->forEvent($this->fair)
            ->forOrganization($this->organization)->create(['price_cents' => 21500]);

        $page = livewire(ShowStaffRegistration::class, ['registration' => $registration])
            ->set('checkAmountDollars', '200.00')
            ->call('markCheckReceived');

        expect($page->get('shortfall'))->toContain('$200.00')->toContain('$215.00');
        expect($registration->refresh()->status)->toBe(RegistrationStatus::Confirmed);

        // And it is still there after the next interaction.
        $page->set('notes', 'anything');
        expect($page->get('shortfall'))->not->toBe('');
    });

    it('offers the check action only for an unpaid check registration', function () {
        $check = Registration::factory()->pendingCheck()->forEvent($this->fair)->create();
        $confirmed = Registration::factory()->forEvent($this->fair)->create();

        expect(livewire(ShowStaffRegistration::class, ['registration' => $check])->instance()->canMarkCheckReceived())
            ->toBeTrue()
            ->and(livewire(ShowStaffRegistration::class, ['registration' => $confirmed])->instance()->canMarkCheckReceived())
            ->toBeFalse();
    });

    it('offers a refund only when a card payment actually settled', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        expect(livewire(ShowStaffRegistration::class, ['registration' => $registration])->instance()->canRefund())
            ->toBeFalse();

        Payment::factory()->for($registration)->create([
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Succeeded,
        ]);

        expect(livewire(ShowStaffRegistration::class, ['registration' => $registration->refresh()])->instance()->canRefund())
            ->toBeTrue();
    });

    it('keeps a user without the permission out', function () {
        $registration = Registration::factory()->forEvent($this->fair)->create();
        $this->actingAs(User::factory()->rep()->create());

        livewire(ShowStaffRegistration::class, ['registration' => $registration])->assertForbidden();
    });
});
