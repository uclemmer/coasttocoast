<?php

use App\Enums\MembershipStatus;
use App\Events\MembershipClaimed;
use App\Events\OrganizationCreated;
use App\Livewire\Auth\Register;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

describe('adding a school that is not in the directory', function () {
    it('creates the school and makes the founder active immediately', function () {
        // Nobody can vouch for a school only this person knows about, so
        // making them wait would mean waiting on nothing (D9).
        livewire(Register::class)
            ->set('name', 'Dana Whitfield')->set('email', 'dana@newschool.example')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'create')->set('organization_name', 'Newschool University')->set('organization_website', 'https://newschool.example')->set('organization_admissions_email', 'admissions@newschool.example')
            ->call('register')
            ->assertHasNoErrors();

        $rep = User::query()->where('email', 'dana@newschool.example')->firstOrFail();
        $school = Organization::query()->where('name', 'Newschool University')->firstOrFail();

        expect($rep->organization_id)->toBe($school->id)
            ->and($rep->membership_status)->toBe(MembershipStatus::Active)
            ->and($rep->actsForOrganization())->toBeTrue()
            ->and($school->created_by)->toBe($rep->id)
            ->and($school->admissions_email)->toBe('admissions@newschool.example')
            // Derived on save, so the duplicate check and the import both work.
            ->and($school->normalized_name)->toBe('newschool university');
    });

    it('alerts the coordinator, carrying the duplicate warning the rep pressed past', function () {
        Event::fake([OrganizationCreated::class]);
        Organization::factory()->named('The Example College')->create();

        livewire(Register::class)
            ->set('name', 'Dana')->set('email', 'dana@example.edu')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'create')->set('organization_name', 'Example College')
            ->call('register')
            ->assertHasNoErrors();

        Event::assertDispatched(
            OrganizationCreated::class,
            fn (OrganizationCreated $e): bool => $e->possibleDuplicates === ['The Example College'],
        );
    });

    it('warns about a near-duplicate without blocking the signup', function () {
        // R2.7. A false positive that stops a school registering is worse than
        // one the coordinator merges later.
        Organization::factory()->named('The Example College')->create();

        livewire(Register::class)
            ->set('organization_choice', 'create')
            ->set('organization_name', 'example college')
            ->assertSuccessful();

        expect(Organization::query()->matchingName('Example College')->count())->toBe(1);
    });

    it('requires a school name on this path', function () {
        livewire(Register::class)
            ->set('name', 'Dana')->set('email', 'dana@example.edu')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'create')->set('organization_name', '')
            ->call('register')
            ->assertHasErrors(['organization_name']);
    });
});

describe('claiming a school that already exists', function () {
    it('leaves the rep pending until a coordinator approves', function () {
        // Anyone can say they represent Vanderbilt, and the school's history,
        // grants and roster entry sit on the other side of that claim.
        $school = Organization::factory()->named('Vanderbilt University')->create();

        livewire(Register::class)
            ->set('name', 'Jamie Okafor')->set('email', 'jamie@vanderbilt.example')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'claim')->set('organization_id', $school->id)
            ->call('register')
            ->assertHasNoErrors();

        $rep = User::query()->where('email', 'jamie@vanderbilt.example')->firstOrFail();

        expect($rep->organization_id)->toBe($school->id)
            ->and($rep->membership_status)->toBe(MembershipStatus::Pending)
            ->and($rep->actsForOrganization())->toBeFalse();
    });

    it('alerts the coordinator, who is the only thing between this person and an indefinite wait', function () {
        Event::fake([MembershipClaimed::class]);
        $school = Organization::factory()->create();

        livewire(Register::class)
            ->set('name', 'Jamie')->set('email', 'jamie@example.edu')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'claim')->set('organization_id', $school->id)
            ->call('register')
            ->assertHasNoErrors();

        Event::assertDispatched(
            MembershipClaimed::class,
            fn (MembershipClaimed $e): bool => $e->organization->is($school),
        );
    });

    it('requires a school to be chosen on this path', function () {
        livewire(Register::class)
            ->set('name', 'Jamie')->set('email', 'jamie@example.edu')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'claim')->set('organization_id', null)
            ->call('register')
            ->assertHasErrors(['organization_id']);
    });
});

describe('the account itself', function () {
    it('records an optional phone without opting anyone in to SMS', function () {
        // Opt-in is a separate, deliberate act (privacy N3). Having a number
        // is not consent to be texted.
        $school = Organization::factory()->create();

        livewire(Register::class)
            ->set('name', 'Jamie')->set('email', 'jamie@example.edu')->set('phone', '+15551234567')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'claim')->set('organization_id', $school->id)
            ->call('register')
            ->assertHasNoErrors();

        expect(User::query()->where('email', 'jamie@example.edu')->first())
            ->phone->toBe('+15551234567')
            ->sms_opt_in->toBeFalse();
    });

    it('refuses an email that already has an account', function () {
        User::factory()->create(['email' => 'taken@example.edu']);
        $school = Organization::factory()->create();

        livewire(Register::class)
            ->set('name', 'Jamie')->set('email', 'taken@example.edu')->set('password', 'password-that-is-long')->set('password_confirmation', 'password-that-is-long')->set('organization_choice', 'claim')->set('organization_id', $school->id)
            ->call('register')
            ->assertHasErrors(['email']);
    });
});
