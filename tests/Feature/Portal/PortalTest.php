<?php

use App\Enums\GrantStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Livewire\Portal\CreateRegistration;
use App\Livewire\Portal\Grants as ListGrants;
use App\Livewire\Portal\OrganizationProfile;
use App\Livewire\Portal\Profile as EditProfile;
use App\Livewire\Portal\Registrations as ListRegistrations;
use App\Livewire\Portal\ShowRegistration as ViewRegistration;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {

    $this->organization = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->organization)->create();
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();

    $this->actingAs($this->rep);
});

describe('the registrations list', function () {
    it('shows the organization\'s registrations, not just this person\'s', function () {
        // A new admissions officer inheriting the account should see the
        // organization's history, not an empty page (D8).
        $predecessor = User::factory()->retiredRep($this->organization)->create();
        $theirs = Registration::factory()->forOrganization($this->organization)
            ->create(['user_id' => $predecessor->id]);
        $mine = Registration::factory()->forOrganization($this->organization)
            ->create(['user_id' => $this->rep->id]);

        livewire(ListRegistrations::class)
            ->assertSee($theirs->event->name)
            ->assertSee($mine->event->name);
    });

    it('never shows another organization\'s registrations', function () {
        $mine = Registration::factory()->forOrganization($this->organization)->create();
        $theirs = Registration::factory()->create();

        /*
         * Asserted against the scoped collection rather than the rendered page.
         * A fair's NAME can legitimately appear on a portal page without that
         * organization's record being visible - the grants page lists every fair you
         * could apply for - so string-matching the HTML tests the wrong thing.
         */
        $listed = livewire(ListRegistrations::class)->instance()->registrations();

        expect($listed->pluck('id'))->toContain($mine->id)
            ->not->toContain($theirs->id);
    });

    it('refuses the page outright to a user with no organization', function () {
        // Not "shows everything" — the scope's `whereRaw('1 = 0')` guards the
        // query, and this guards the page.
        Registration::factory()->count(3)->create();
        $this->actingAs(User::factory()->create());

        livewire(ListRegistrations::class)->assertForbidden();
    });

    it('explains a pending membership rather than just hiding the button', function () {
        $pending = User::factory()->pendingRep($this->organization)->create();
        $this->actingAs($pending);

        $subheading = livewire(ListRegistrations::class)->html();

        expect($subheading)->toContain('confirming that you work at')
            ->toContain('Kenyon College');
    });

    it('says what a retired rep can and cannot still do', function () {
        $this->actingAs(User::factory()->retiredRep($this->organization)->create());

        expect(livewire(ListRegistrations::class)->html())
            ->toContain('retired')
            ->toContain('history is still here');
    });
});

describe('viewing one registration', function () {
    it('shows the fair, the amount and who is staffing the table', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['price_cents' => 21500, 'rep_name' => 'Dana Whitfield']);

        livewire(ViewRegistration::class, ['registration' => $registration])
            ->assertSuccessful()
            ->assertSee('Dana Whitfield')
            ->assertSee('$215.00');
    });

    it('refuses another organization\'s registration', function () {
        // Test-inventory item 14. The resource scope means the record is not
        // found at all, which beats "found, but forbidden" — the latter
        // confirms a registration with that id exists.
        $theirs = Registration::factory()->create();

        expect(fn () => livewire(ViewRegistration::class, ['registration' => $theirs]))
            ->toThrow(ModelNotFoundException::class);
    });
});

describe('the registration wizard', function () {
    it('registers the organization and holds the place pending payment', function () {
        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana Whitfield')->set('rep_email', 'dana@kenyon.example')->set('rep_phone', '(423) 757-2845')->set('payment_method', PaymentMethod::Check->value)
            ->call('submit')
            ->assertHasNoErrors();

        $registration = Registration::query()->latest('id')->firstOrFail();

        expect($registration->organization_id)->toBe($this->organization->id)
            ->and($registration->user_id)->toBe($this->rep->id)
            ->and($registration->status)->toBe(RegistrationStatus::PendingPayment)
            ->and($registration->price_cents)->toBe(21500)
            // Normalised on the way in, so Twilio can actually use it.
            ->and($registration->rep_phone)->toBe('+14237572845');
    });

    it('snapshots the grant-aware price, which the form never accepts as input', function () {
        // There is no price field and no argument for one — "the client set
        // the price" is unrepresentable, not merely checked for (N1).
        Grant::factory()->percentOff(50)->for($this->fair)->for($this->organization)->create();

        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')->set('payment_method', PaymentMethod::Stripe->value)
            ->call('submit')
            ->assertHasNoErrors();

        expect(Registration::query()->latest('id')->first()->price_cents)->toBe(10750);
    });

    it('confirms a fully-granted registration with no payment step at all', function () {
        Grant::factory()->free()->for($this->fair)->for($this->organization)->create();

        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')
            ->call('submit')
            ->assertHasNoErrors();

        $registration = Registration::query()->latest('id')->firstOrFail();

        expect($registration->status)->toBe(RegistrationStatus::Confirmed)
            ->and($registration->payment_method)->toBeNull()
            ->and($registration->payments()->count())->toBe(0);
    });

    it('demands a payment method when there is something to pay', function () {
        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')->set('payment_method', null)
            ->call('submit')
            ->assertHasErrors(['payment_method']);
    });

    it('refuses a duplicate at the first step, not after the whole wizard', function () {
        Registration::factory()->forEvent($this->fair)->forOrganization($this->organization)->create();

        livewire(CreateRegistration::class)
            ->set('event_id', $this->fair->id)->set('rep_name', 'Dana')->set('rep_email', 'dana@kenyon.example')->set('payment_method', PaymentMethod::Check->value)
            ->call('submit')
            ->assertHasErrors(['event_id']);
    });

    it('offers only fairs that are open', function () {
        Fair::factory()->registrationClosed()->create();
        Fair::factory()->registrationNotYetOpen()->create();
        Fair::factory()->create(); // unpublished

        $page = livewire(CreateRegistration::class)->instance();
        $options = $page->openFairs()->mapWithKeys(
            fn ($fair): array => [$fair->getKey() => $page->fairLabel($fair)],
        )->all();

        expect(array_keys($options))->toBe([$this->fair->id]);
    });

    it('shows the grant-aware price and explains the discount', function () {
        // A discount nobody explains is a discount somebody queries.
        Grant::factory()->percentOff(50)->for($this->fair)->for($this->organization)->create();

        $fairId = $this->fair->id;
        $summary = livewire(CreateRegistration::class)
            ->set('event_id', $fairId)
            ->instance()
            ->priceSummary();

        expect($summary)->toContain('$215.00')->toContain('$107.50')->toContain('50% off');
    });

    it('bars a pending rep outright', function () {
        $this->actingAs(User::factory()->pendingRep($this->organization)->create());

        livewire(CreateRegistration::class)->assertForbidden();
    });

    it('bars a retired rep outright', function () {
        $this->actingAs(User::factory()->retiredRep($this->organization)->create());

        livewire(CreateRegistration::class)->assertForbidden();
    });
});

describe('the organization profile', function () {
    it('saves the organization\'s details and normalizes the phone', function () {
        livewire(OrganizationProfile::class)
            ->set('name', 'Kenyon College')->set('website', 'https://kenyon.example')->set('admissions_email', 'admissions@kenyon.example')->set('admissions_phone', '(423) 757-2845')
            ->call('save');

        expect($this->organization->refresh()->admissions_email)->toBe('admissions@kenyon.example')
            ->and($this->organization->admissions_phone)->toBe('+14237572845');
    });

    it('re-derives the matching name when an organization rebrands', function () {
        livewire(OrganizationProfile::class)
            ->set('name', 'The Kenyon University')
            ->call('save');

        expect($this->organization->refresh()->normalized_name)->toBe('kenyon university');
    });

    it('rejects a phone number nobody could dial', function () {
        livewire(OrganizationProfile::class)
            ->set('name', 'Kenyon College')
            ->set('admissions_phone', '12')
            ->call('save')
            ->assertHasErrors(['admissions_phone']);
    });

    it('bars pending and retired reps', function (string $state) {
        $this->actingAs(User::factory()->{$state}($this->organization)->create());

        livewire(OrganizationProfile::class)->assertForbidden();
    })->with(['pendingRep', 'retiredRep']);
});

describe('the personal profile', function () {
    it('saves a phone in E.164 without opting anyone in', function () {
        livewire(EditProfile::class)
            ->set('name', $this->rep->name)->set('email', $this->rep->email)->set('phone', '423-757-2845')
            ->call('save');

        expect($this->rep->refresh()->phone)->toBe('+14237572845')
            // Having a number is not consent to be texted (N3).
            ->and($this->rep->sms_opt_in)->toBeFalse();
    });

    it('records an explicit SMS opt-in', function () {
        livewire(EditProfile::class)
            ->set('name', $this->rep->name)->set('email', $this->rep->email)->set('phone', '+14237572845')->set('sms_opt_in', true)
            ->call('save');

        expect($this->rep->refresh()->sms_opt_in)->toBeTrue();
    });

    it('rejects a phone number nobody could dial', function () {
        livewire(EditProfile::class)
            ->set('name', $this->rep->name)->set('email', $this->rep->email)
            ->set('phone', '12')
            ->call('save')
            ->assertHasErrors(['phone']);
    });

    it('lets a rep step down, keeping the account and the history', function () {
        Registration::factory()->forOrganization($this->organization)->create(['user_id' => $this->rep->id]);

        livewire(EditProfile::class)->call('retire');

        expect($this->rep->refresh()->membership_status)->toBe(MembershipStatus::Retired)
            ->and($this->rep->retired_by)->toBe($this->rep->id)
            ->and($this->rep->actsForOrganization())->toBeFalse()
            ->and($this->organization->registrations()->count())->toBe(1);
    });

    it('hides stepping down from somebody who has already stepped down', function () {
        $this->actingAs(User::factory()->retiredRep($this->organization)->create());

        expect(livewire(EditProfile::class)->instance()->actsForOrganization())->toBeFalse();
    });
});

describe('fee assistance', function () {
    it('submits a request with the owner-approved copy', function () {
        livewire(ListGrants::class)
            ->set('event_id', $this->fair->id)->set('justification', 'Our travel budget was cut this year.')->call('apply')
            ->assertDispatched(
                'ui-toast',
                message: "Request submitted — we'll email you when it's been reviewed.",
            );

        expect(Grant::query()->where('organization_id', $this->organization->id)->first())
            ->justification->toBe('Our travel budget was cut this year.')
            ->status->toBe(GrantStatus::Pending);
    });

    it('shows the intro that stops an organization waiting instead of registering', function () {
        expect(livewire(ListGrants::class)->html())
            ->toContain('does not register you for the fair');
    });

    it('renders the approved sentence with the actual benefit', function () {
        $grant = Grant::factory()->percentOff(25)->for($this->fair)->for($this->organization)->create();

        expect(livewire(ListGrants::class)->instance()->statusCopy($grant->refresh()))
            ->toContain('Good news')
            ->toContain('25% off')
            ->toContain($this->fair->name);
    });

    it('includes the coordinator\'s reason in a denial', function () {
        $grant = Grant::factory()->denied('Funds for this fair are already committed.')
            ->for($this->fair)->for($this->organization)->create();

        expect(livewire(ListGrants::class)->instance()->statusCopy($grant->refresh()))
            ->toContain('Funds for this fair are already committed.')
            ->toContain('Standard registration is still open');
    });

    it('withdraws a pending request and frees the organization to apply again', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->organization)
            ->create(['requested_by' => $this->rep->id]);

        livewire(ListGrants::class)
            ->call('confirmWithdraw', $grant->id)
            ->call('withdraw');

        expect($grant->refresh()->status)->toBe(GrantStatus::Withdrawn);

        livewire(ListGrants::class)
            ->set('event_id', $this->fair->id)
            ->set('justification', 'Second attempt, with figures.')
            ->call('apply');

        expect(Grant::query()->where('status', GrantStatus::Pending)->count())->toBe(1);
    });

    it('hides the apply action from pending and retired reps', function (string $state) {
        $this->actingAs(User::factory()->{$state}($this->organization)->create());

        expect(livewire(ListGrants::class)->instance()->canApply())->toBeFalse();
    })->with(['pendingRep', 'retiredRep']);

    it('hides the apply action when there is no fair left to apply for', function () {
        Grant::factory()->for($this->fair)->for($this->organization)->create();

        expect(livewire(ListGrants::class)->instance()->canApply())->toBeFalse();
    });

    it('never shows another organization\'s requests', function () {
        $mine = Grant::factory()->for($this->fair)->for($this->organization)->create();
        $theirs = Grant::factory()->for($this->fair)->create();

        livewire(ListGrants::class)
            ->assertSee($mine->event->name);

        expect(livewire(ListGrants::class)->instance()->grants()->pluck('id'))
            ->toContain($mine->id)
            ->not->toContain($theirs->id);
    });
});
