<?php

use App\Enums\Audience;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Event as Fair;
use App\Models\EventInterest;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\AudienceBuilder;
use App\Services\Audiences\RecipientDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->builder = app(AudienceBuilder::class);

    // Three fairs, so LastEvent and AnyPreviousEvent are distinguishable —
    // the whole reason the seeders insist on two past years.
    $this->twoYearsAgo = Fair::factory()->past(2)->create();
    $this->lastYear = Fair::factory()->past(1)->create();
    $this->thisYear = Fair::factory()->published()->create();
});

/**
 * A school with one active rep, registered for the given fairs.
 *
 * @param  array<int, Event>  $fairs
 */
function school(string $name, array $fairs = [], array $registrationState = []): Organization
{
    $organization = Organization::factory()->named($name)->create();
    User::factory()->rep($organization)->create(['email' => str($name)->slug().'@example.edu']);

    foreach ($fairs as $fair) {
        Registration::factory()
            ->forEvent($fair)
            ->forOrganization($organization)
            ->create($registrationState);
    }

    return $organization;
}

describe('this fair', function () {
    it('finds confirmed schools', function () {
        school('Kenyon College', [$this->thisYear]);
        school('Pending College', [$this->thisYear], ['status' => RegistrationStatus::PendingPayment]);

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear)->pluck('organizationName')->all())
            ->toBe(['Kenyon College']);
    });

    it('finds schools whose check has not arrived', function () {
        $waiting = Organization::factory()->named('Waiting College')->create();
        User::factory()->rep($waiting)->create();
        Registration::factory()->pendingCheck()->forEvent($this->thisYear)->forOrganization($waiting)->create();

        school('Paid College', [$this->thisYear]);

        expect($this->builder->resolve(Audience::ThisEventPendingCheck, $this->thisYear)->pluck('organizationName')->all())
            ->toBe(['Waiting College']);
    });

    it('finds everyone with a live registration', function () {
        school('Confirmed College', [$this->thisYear]);
        $pending = Organization::factory()->named('Pending College')->create();
        User::factory()->rep($pending)->create();
        Registration::factory()->pendingCheck()->forEvent($this->thisYear)->forOrganization($pending)->create();

        expect($this->builder->resolve(Audience::ThisEventAll, $this->thisYear))->toHaveCount(2);
    });

    it('never counts a cancelled or refunded registration', function () {
        // Rule 4: they did not attend.
        school('Cancelled College', [$this->thisYear], ['status' => RegistrationStatus::Cancelled]);
        school('Refunded College', [$this->thisYear], ['status' => RegistrationStatus::Refunded]);

        expect($this->builder->resolve(Audience::ThisEventAll, $this->thisYear))->toBeEmpty()
            ->and($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear))->toBeEmpty();
    });
});

describe('cross-year audiences', function () {
    it('separates last year from any previous year', function () {
        // Indistinguishable with one year of history, which is why the
        // fixtures insist on two.
        school('Last Year College', [$this->lastYear]);
        school('Long Ago College', [$this->twoYearsAgo]);

        expect($this->builder->resolve(Audience::LastEvent, $this->thisYear)->pluck('organizationName')->all())
            ->toBe(['Last Year College'])
            ->and($this->builder->resolve(Audience::AnyPreviousEvent, $this->thisYear)->pluck('organizationName')->sort()->values()->all())
            ->toBe(['Last Year College', 'Long Ago College']);
    });

    it('subtracts this year from the lapsed lists', function () {
        // The win-back list: attended before, not yet registered now.
        school('Returning College', [$this->lastYear, $this->thisYear]);
        school('Lapsed College', [$this->lastYear]);

        expect($this->builder->resolve(Audience::LapsedLastEvent, $this->thisYear)->pluck('organizationName')->all())
            ->toBe(['Lapsed College'])
            ->and($this->builder->resolve(Audience::LapsedAnyPrevious, $this->thisYear)->pluck('organizationName')->all())
            ->toBe(['Lapsed College']);
    });

    it('counts a school with a check in the post as registered, so it is not chased', function () {
        $school = Organization::factory()->named('Paying College')->create();
        User::factory()->rep($school)->create();
        Registration::factory()->forEvent($this->lastYear)->forOrganization($school)->create();
        Registration::factory()->pendingCheck()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::LapsedLastEvent, $this->thisYear))->toBeEmpty();
    });

    it('treats a cancelled registration this year as not registered', function () {
        // They cancelled; they belong on the win-back list.
        $school = Organization::factory()->named('Changed Mind College')->create();
        User::factory()->rep($school)->create();
        Registration::factory()->forEvent($this->lastYear)->forOrganization($school)->create();
        Registration::factory()->cancelled()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::LapsedLastEvent, $this->thisYear))->toHaveCount(1);
    });

    it('ignores an unpublished past fair', function () {
        // Rule 5: "previous" means published, the same definition the Last
        // Year page uses.
        $draft = Fair::factory()->past(1)->create(['is_published' => false]);
        school('Draft College', [$draft]);

        expect($this->builder->resolve(Audience::AnyPreviousEvent, $this->thisYear))->toBeEmpty();
    });
});

describe('organizations qualify, people receive', function () {
    it('delivers to every active rep of a qualifying school', function () {
        $school = Organization::factory()->named('Kenyon College')->create();
        User::factory()->count(2)->rep($school)->create();
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear))->toHaveCount(2);
    });

    it('never mails a pending or retired rep', function () {
        // R2.10. They can still see their history; they are not the school's
        // voice any more.
        $school = Organization::factory()->named('Kenyon College')->create();
        $active = User::factory()->rep($school)->create(['email' => 'active@kenyon.example']);
        User::factory()->pendingRep($school)->create(['email' => 'pending@kenyon.example']);
        User::factory()->retiredRep($school)->create(['email' => 'retired@kenyon.example']);
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear)->pluck('email')->all())
            ->toBe([$active->email]);
    });

    it('falls back to the admissions email when nobody is active', function () {
        $school = Organization::factory()->named('Maryville College')->create([
            'admissions_email' => 'admissions@maryville.example',
        ]);
        User::factory()->retiredRep($school)->create();
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        $recipients = $this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear);

        expect($recipients)->toHaveCount(1)
            ->and($recipients->first())
            ->email->toBe('admissions@maryville.example')
            // Flagged, so the coordinator can see how much of a send is going
            // to nobody in particular.
            ->generic->toBeTrue()
            ->userId->toBeNull();
    });

    it('drops a school with neither, and says so in the log', function () {
        // A school vanishing without a trace is how it stops being invited.
        Log::spy();

        $school = Organization::factory()->named('Bryan College')->withoutAdmissionsEmail()->create();
        User::factory()->retiredRep($school)->create();
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear))->toBeEmpty();

        Log::shouldHaveReceived('info')->once();
    });

    it('can be told to skip the generic fallback entirely', function () {
        $school = Organization::factory()->named('Maryville College')->create([
            'admissions_email' => 'admissions@maryville.example',
        ]);
        User::factory()->retiredRep($school)->create();
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear, ['skipGenericFallback' => true]))
            ->toBeEmpty();
    });
});

describe('dedupe', function () {
    it('emails a rep once however many years they qualify through', function () {
        // Rule 2. A rep active across three past years qualifies three times.
        $school = Organization::factory()->named('Kenyon College')->create();
        User::factory()->rep($school)->create(['email' => 'dana@kenyon.example']);
        Registration::factory()->forEvent($this->twoYearsAgo)->forOrganization($school)->create();
        Registration::factory()->forEvent($this->lastYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::AnyPreviousEvent, $this->thisYear))->toHaveCount(1);
    });

    it('dedupes by address when there is no account', function () {
        EventInterest::factory()->for($this->thisYear)->create(['email' => 'Dana@Kenyon.example']);
        EventInterest::factory()->for($this->thisYear)->create(['email' => 'dana@kenyon.example']);

        expect($this->builder->resolve(Audience::InterestList, $this->thisYear))->toHaveCount(1);
    });
});

describe('the interest list', function () {
    it('resolves to bare addresses with no school and no account', function () {
        EventInterest::factory()->for($this->thisYear)->create([
            'email' => 'dana@kenyon.example',
            'organization_name' => 'Kenyon College',
        ]);
        EventInterest::factory()->for($this->lastYear)->create();

        $recipients = $this->builder->resolve(Audience::InterestList, $this->thisYear);

        expect($recipients)->toHaveCount(1)
            ->and($recipients->first())
            ->email->toBe('dana@kenyon.example')
            ->organizationName->toBe('Kenyon College')
            ->organizationId->toBeNull()
            ->userId->toBeNull();
    });
});

describe('filters', function () {
    it('narrows to recipients who can actually receive a text', function () {
        $school = Organization::factory()->create();
        $optedIn = User::factory()->rep($school)->smsOptedIn()->create();
        User::factory()->rep($school)->create(['phone' => '+15551234567', 'sms_opt_in' => false]);
        User::factory()->rep($school)->create(['phone' => null, 'sms_opt_in' => true]);
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear, ['smsOptedInOnly' => true])
            ->pluck('email')->all())->toBe([$optedIn->email]);
    });

    it('honours a manual suppression list, case-insensitively', function () {
        $school = Organization::factory()->create();
        User::factory()->rep($school)->create(['email' => 'noisy@kenyon.example']);
        $keep = User::factory()->rep($school)->create(['email' => 'quiet@kenyon.example']);
        Registration::factory()->forEvent($this->thisYear)->forOrganization($school)->create();

        expect($this->builder->resolve(Audience::ThisEventConfirmed, $this->thisYear, [
            'excludeEmails' => ['NOISY@kenyon.example'],
        ])->pluck('email')->all())->toBe([$keep->email]);
    });
});

describe('resolution at send time', function () {
    it('reflects the world as it is when it runs, not when the message was written', function () {
        // Rule 6. Schedule a note to lapsed schools and whoever is lapsed when
        // it fires is who gets it.
        school('Lapsed College', [$this->lastYear]);

        expect($this->builder->count(Audience::LapsedLastEvent, $this->thisYear))->toBe(1);

        Registration::factory()
            ->forEvent($this->thisYear)
            ->forOrganization(Organization::query()->where('name', 'Lapsed College')->firstOrFail())
            ->create();

        expect($this->builder->count(Audience::LapsedLastEvent, $this->thisYear))->toBe(0);
    });

    it('returns nothing rather than everything when there is no reference fair', function () {
        Fair::query()->update(['is_published' => false]);

        expect($this->builder->resolve(Audience::ThisEventAll, null))->toBeEmpty();
    });
});

describe('the recipient DTO', function () {
    it('keys dedupe on the account when there is one and the address otherwise', function () {
        $withAccount = new RecipientDto(email: 'Dana@Kenyon.example', userId: 7);
        $without = new RecipientDto(email: 'Dana@Kenyon.example');

        expect($withAccount->dedupeKey())->toBe('user:7')
            ->and($without->dedupeKey())->toBe('email:dana@kenyon.example');
    });

    it('needs both an opt-in and a number to be textable', function () {
        expect((new RecipientDto(email: 'a@b.c', phone: '+15551234567', smsOptIn: true))->canReceiveSms())->toBeTrue()
            ->and((new RecipientDto(email: 'a@b.c', phone: '+15551234567'))->canReceiveSms())->toBeFalse()
            ->and((new RecipientDto(email: 'a@b.c', smsOptIn: true))->canReceiveSms())->toBeFalse();
    });
});
