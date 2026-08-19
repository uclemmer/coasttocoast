<?php

use App\Enums\GrantStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Filament\Rep\Pages\Auth\EditProfile;
use App\Filament\Rep\Pages\OrganizationProfile;
use App\Filament\Rep\Resources\GrantResource;
use App\Filament\Rep\Resources\GrantResource\Pages\ListGrants;
use App\Filament\Rep\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Filament\Rep\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Filament\Rep\Resources\RegistrationResource\Pages\ViewRegistration;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    usingRepPanel();

    $this->school = Organization::factory()->named('Kenyon College')->create();
    $this->rep = User::factory()->rep($this->school)->create();
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();

    $this->actingAs($this->rep);
});

describe('the registrations list', function () {
    it('shows the school\'s registrations, not just this person\'s', function () {
        // A new admissions officer inheriting the account should see the
        // school's history, not an empty page (D8).
        $predecessor = User::factory()->retiredRep($this->school)->create();
        $theirs = Registration::factory()->forOrganization($this->school)
            ->create(['user_id' => $predecessor->id]);
        $mine = Registration::factory()->forOrganization($this->school)
            ->create(['user_id' => $this->rep->id]);

        livewire(ListRegistrations::class)
            ->assertCanSeeTableRecords([$theirs, $mine]);
    });

    it('never shows another school\'s registrations', function () {
        $mine = Registration::factory()->forOrganization($this->school)->create();
        $theirs = Registration::factory()->create();

        livewire(ListRegistrations::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    });

    it('refuses the page outright to a user with no school', function () {
        // Not "shows everything" — the scope's `whereRaw('1 = 0')` guards the
        // query, and this guards the page.
        Registration::factory()->count(3)->create();
        $this->actingAs(User::factory()->create());

        livewire(ListRegistrations::class)->assertForbidden();
    });

    it('explains a pending membership rather than just hiding the button', function () {
        $pending = User::factory()->pendingRep($this->school)->create();
        $this->actingAs($pending);

        $subheading = livewire(ListRegistrations::class)->instance()->getSubheading();

        expect($subheading)->toContain('confirming that you work at')
            ->toContain('Kenyon College');
    });

    it('says what a retired rep can and cannot still do', function () {
        $this->actingAs(User::factory()->retiredRep($this->school)->create());

        expect(livewire(ListRegistrations::class)->instance()->getSubheading())
            ->toContain('retired')
            ->toContain('history is still here');
    });
});

describe('viewing one registration', function () {
    it('shows the fair, the amount and who is staffing the table', function () {
        $registration = Registration::factory()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['price_cents' => 21500, 'rep_name' => 'Dana Whitfield']);

        livewire(ViewRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Dana Whitfield')
            ->assertSee('$215.00');
    });

    it('refuses another school\'s registration', function () {
        // Test-inventory item 14. The resource scope means the record is not
        // found at all, which beats "found, but forbidden" — the latter
        // confirms a registration with that id exists.
        $theirs = Registration::factory()->create();

        expect(fn () => livewire(ViewRegistration::class, ['record' => $theirs->getRouteKey()]))
            ->toThrow(ModelNotFoundException::class);
    });
});

describe('the registration wizard', function () {
    it('registers the school and holds the place pending payment', function () {
        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'rep_name' => 'Dana Whitfield',
                'rep_email' => 'dana@kenyon.example',
                'rep_phone' => '(423) 757-2845',
                'payment_method' => PaymentMethod::Check->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $registration = Registration::query()->latest('id')->firstOrFail();

        expect($registration->organization_id)->toBe($this->school->id)
            ->and($registration->user_id)->toBe($this->rep->id)
            ->and($registration->status)->toBe(RegistrationStatus::PendingPayment)
            ->and($registration->price_cents)->toBe(21500)
            // Normalised on the way in, so Twilio can actually use it.
            ->and($registration->rep_phone)->toBe('+14237572845');
    });

    it('snapshots the grant-aware price, which the form never accepts as input', function () {
        // There is no price field and no argument for one — "the client set
        // the price" is unrepresentable, not merely checked for (N1).
        Grant::factory()->percentOff(50)->for($this->fair)->for($this->school)->create();

        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'rep_name' => 'Dana',
                'rep_email' => 'dana@kenyon.example',
                'payment_method' => PaymentMethod::Stripe->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Registration::query()->latest('id')->first()->price_cents)->toBe(10750);
    });

    it('confirms a fully-granted registration with no payment step at all', function () {
        Grant::factory()->free()->for($this->fair)->for($this->school)->create();

        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'rep_name' => 'Dana',
                'rep_email' => 'dana@kenyon.example',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $registration = Registration::query()->latest('id')->firstOrFail();

        expect($registration->status)->toBe(RegistrationStatus::Confirmed)
            ->and($registration->payment_method)->toBeNull()
            ->and($registration->payments()->count())->toBe(0);
    });

    it('demands a payment method when there is something to pay', function () {
        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'rep_name' => 'Dana',
                'rep_email' => 'dana@kenyon.example',
                'payment_method' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['payment_method']);
    });

    it('refuses a duplicate at the first step, not after the whole wizard', function () {
        Registration::factory()->forEvent($this->fair)->forOrganization($this->school)->create();

        livewire(CreateRegistration::class)
            ->fillForm([
                'event_id' => $this->fair->id,
                'rep_name' => 'Dana',
                'rep_email' => 'dana@kenyon.example',
                'payment_method' => PaymentMethod::Check->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['event_id']);
    });

    it('offers only fairs that are open', function () {
        Fair::factory()->registrationClosed()->create();
        Fair::factory()->registrationNotYetOpen()->create();
        Fair::factory()->create(); // unpublished

        $page = livewire(CreateRegistration::class)->instance();
        $options = (fn (): array => $this->openFairs())->call($page);

        expect(array_keys($options))->toBe([$this->fair->id]);
    });

    it('shows the grant-aware price and explains the discount', function () {
        // A discount nobody explains is a discount somebody queries.
        Grant::factory()->percentOff(50)->for($this->fair)->for($this->school)->create();

        $fairId = $this->fair->id;
        $page = livewire(CreateRegistration::class)->fillForm(['event_id' => $fairId])->instance();

        $summary = (fn (): string => $this->priceSummary($fairId))->call($page);

        expect($summary)->toContain('$215.00')->toContain('$107.50')->toContain('50% off');
    });

    it('bars a pending rep outright', function () {
        $this->actingAs(User::factory()->pendingRep($this->school)->create());

        livewire(CreateRegistration::class)->assertForbidden();
    });

    it('bars a retired rep outright', function () {
        $this->actingAs(User::factory()->retiredRep($this->school)->create());

        livewire(CreateRegistration::class)->assertForbidden();
    });
});

describe('the organization profile', function () {
    it('saves the school\'s details and normalizes the phone', function () {
        livewire(OrganizationProfile::class)
            ->fillForm([
                'name' => 'Kenyon College',
                'website' => 'https://kenyon.example',
                'admissions_email' => 'admissions@kenyon.example',
                'admissions_phone' => '(423) 757-2845',
            ])
            ->call('save');

        expect($this->school->refresh()->admissions_email)->toBe('admissions@kenyon.example')
            ->and($this->school->admissions_phone)->toBe('+14237572845');
    });

    it('re-derives the matching name when a school rebrands', function () {
        livewire(OrganizationProfile::class)
            ->fillForm(['name' => 'The Kenyon University'])
            ->call('save');

        expect($this->school->refresh()->normalized_name)->toBe('kenyon university');
    });

    it('rejects a phone number nobody could dial', function () {
        livewire(OrganizationProfile::class)
            ->fillForm(['name' => 'Kenyon College', 'admissions_phone' => '12'])
            ->call('save')
            ->assertHasFormErrors(['admissions_phone']);
    });

    it('bars pending and retired reps', function (string $state) {
        $this->actingAs(User::factory()->{$state}($this->school)->create());

        livewire(OrganizationProfile::class)->assertForbidden();
    })->with(['pendingRep', 'retiredRep']);
});

describe('the personal profile', function () {
    it('saves a phone in E.164 without opting anyone in', function () {
        livewire(EditProfile::class)
            ->fillForm([
                'name' => $this->rep->name,
                'email' => $this->rep->email,
                'phone' => '423-757-2845',
            ])
            ->call('save');

        expect($this->rep->refresh()->phone)->toBe('+14237572845')
            // Having a number is not consent to be texted (N3).
            ->and($this->rep->sms_opt_in)->toBeFalse();
    });

    it('records an explicit SMS opt-in', function () {
        livewire(EditProfile::class)
            ->fillForm([
                'name' => $this->rep->name,
                'email' => $this->rep->email,
                'phone' => '+14237572845',
                'sms_opt_in' => true,
            ])
            ->call('save');

        expect($this->rep->refresh()->sms_opt_in)->toBeTrue();
    });

    it('rejects a phone number nobody could dial', function () {
        livewire(EditProfile::class)
            ->fillForm(['name' => $this->rep->name, 'email' => $this->rep->email, 'phone' => 'nope'])
            ->call('save')
            ->assertHasFormErrors(['phone']);
    });

    it('lets a rep step down, keeping the account and the history', function () {
        Registration::factory()->forOrganization($this->school)->create(['user_id' => $this->rep->id]);

        livewire(EditProfile::class)->callAction('retire');

        expect($this->rep->refresh()->membership_status)->toBe(MembershipStatus::Retired)
            ->and($this->rep->retired_by)->toBe($this->rep->id)
            ->and($this->rep->actsForOrganization())->toBeFalse()
            ->and($this->school->registrations()->count())->toBe(1);
    });

    it('hides stepping down from somebody who has already stepped down', function () {
        $this->actingAs(User::factory()->retiredRep($this->school)->create());

        livewire(EditProfile::class)->assertActionHidden('retire');
    });
});

describe('fee assistance', function () {
    it('submits a request with the owner-approved copy', function () {
        livewire(ListGrants::class)
            ->callAction('apply', [
                'event_id' => $this->fair->id,
                'justification' => 'Our travel budget was cut this year.',
            ])
            ->assertNotified("Request submitted — we'll email you when it's been reviewed.");

        expect(Grant::query()->where('organization_id', $this->school->id)->first())
            ->justification->toBe('Our travel budget was cut this year.')
            ->status->toBe(GrantStatus::Pending);
    });

    it('shows the intro that stops a school waiting instead of registering', function () {
        expect(livewire(ListGrants::class)->instance()->getSubheading())
            ->toContain('does not register you for the fair');
    });

    it('renders the approved sentence with the actual benefit', function () {
        $grant = Grant::factory()->percentOff(25)->for($this->fair)->for($this->school)->create();

        expect(GrantResource::statusCopy($grant->refresh()))
            ->toContain('Good news')
            ->toContain('25% off')
            ->toContain($this->fair->name);
    });

    it('includes the coordinator\'s reason in a denial', function () {
        $grant = Grant::factory()->denied('Funds for this fair are already committed.')
            ->for($this->fair)->for($this->school)->create();

        expect(GrantResource::statusCopy($grant->refresh()))
            ->toContain('Funds for this fair are already committed.')
            ->toContain('Standard registration is still open');
    });

    it('withdraws a pending request and frees the school to apply again', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->school)
            ->create(['requested_by' => $this->rep->id]);

        livewire(ListGrants::class)->callTableAction('withdraw', $grant);

        expect($grant->refresh()->status)->toBe(GrantStatus::Withdrawn);

        livewire(ListGrants::class)->callAction('apply', [
            'event_id' => $this->fair->id,
            'justification' => 'Second attempt, with figures.',
        ]);

        expect(Grant::query()->where('status', GrantStatus::Pending)->count())->toBe(1);
    });

    it('hides the apply action from pending and retired reps', function (string $state) {
        $this->actingAs(User::factory()->{$state}($this->school)->create());

        livewire(ListGrants::class)->assertActionHidden('apply');
    })->with(['pendingRep', 'retiredRep']);

    it('hides the apply action when there is no fair left to apply for', function () {
        Grant::factory()->for($this->fair)->for($this->school)->create();

        livewire(ListGrants::class)->assertActionHidden('apply');
    });

    it('never shows another school\'s requests', function () {
        $mine = Grant::factory()->for($this->fair)->for($this->school)->create();
        $theirs = Grant::factory()->for($this->fair)->create();

        livewire(ListGrants::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    });
});
