<?php

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\EventSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\ParticipantExportSeeder;
use Database\Seeders\RegistrationSeeder;

/**
 * The previous system's roster, seeded from `storage/app/private/participants.json`.
 *
 * These run the two export seeders on their own — no fixtures — because the
 * fixtures create some of the same organizations by name and would blur every
 * count. `SeederTest` covers what the development seed does with both.
 *
 * **The export is gitignored on purpose** (owner, 2026-09-01) — it is real
 * contact data for ~380 people. So every test here is SKIPPED where the file is
 * not, rather than asserted against an empty database, and the run says which
 * file it wanted.
 *
 * What a machine without the export can still prove is that its absence is
 * loud, and that is `ParticipantExportMissingTest` — a separate file precisely
 * so this one's guard does not skip it.
 */
beforeEach(function () {
    if (! ParticipantExportSeeder::available()) {
        $this->markTestSkipped('No participant export at '.ParticipantExportSeeder::path().'.');
    }

    $this->seed(EventSeeder::class);
    $this->seed(OrganizationSeeder::class);
    $this->seed(RegistrationSeeder::class);
});

it('seeds every organization and every place at a fair from the export', function () {
    // 381 submissions collapse to 354 registrations across 158 organizations:
    // the export is a form log, and people submitted twice.
    expect(Organization::query()->count())->toBe(158)
        ->and(Registration::query()->count())->toBe(354);
});

it('fills the four fairs the export covers and leaves the others empty', function () {
    $rosters = Event::query()->withCount('registrations')->orderBy('starts_at')
        ->pluck('registrations_count', 'slug');

    expect($rosters->all())->toBe([
        // No 2022 rows in the export, and nothing invented to fill it.
        'college-fair-2022' => 0,
        'college-fair-2023' => 72,
        'college-fair-2024' => 87,
        'college-fair-2025' => 99,
        'college-fair-2026' => 96,
        'college-fair-2027' => 0,
    ]);
});

it('gives the cross-year audiences four years of history to difference', function () {
    // The whole point of importing this (doc 07 §2): LastEvent minus
    // AnyPreviousEvent is empty until organizations attend some fairs and not others.
    $organizationsPerFair = Event::query()->orderBy('starts_at')->get()
        ->mapWithKeys(fn (Event $fair): array => [
            $fair->slug => $fair->registrations()->pluck('organization_id'),
        ]);

    expect($organizationsPerFair['college-fair-2025']->diff($organizationsPerFair['college-fair-2026']))->not->toBeEmpty()
        ->and($organizationsPerFair['college-fair-2023']->diff($organizationsPerFair['college-fair-2026']))->not->toBeEmpty();
});

describe('duplicate submissions', function () {
    it('collapses several submissions for one fair into one registration', function () {
        $organization = Organization::query()->matchingName('Connecticut College')->sole();
        $fair = Event::query()->where('slug', 'college-fair-2024')->sole();

        // Four submissions within seven seconds — a form that was clicked four
        // times, not four places at the fair.
        expect($organization->registrations()->where('event_id', $fair->id)->count())->toBe(1);
    });

    it('keeps the most recent submission as the contact of record', function () {
        $registration = registrationFor('Georgia State University', 'college-fair-2023');

        // Two different people signed Georgia State up in 2023, two months
        // apart. The later one is the organization's latest word on who is coming.
        expect($registration->rep_name)->toBe('KeyShawn Phillips')
            ->and($registration->rep_email)->toBe('kphillips46@gsu.edu');
    });

    it('records the submissions it set aside in the notes', function () {
        $registration = registrationFor('Georgia State University', 'college-fair-2023');

        // A colleague who also signed the organization up is worth knowing
        // about before the table is set; dropping the row silently is not.
        expect($registration->notes)->toContain('Andraea Capers <acapers@gsu.edu>');
    });

    it('does not clutter the notes with one person clicking twice', function () {
        $registration = registrationFor('Rollins College', 'college-fair-2024');

        // Both submissions are mvillamizar@rollins.edu, one second apart.
        expect($registration->notes)->not->toContain('Also submitted');
    });

    it('keeps whatever the organization wrote in the message box', function () {
        $registration = registrationFor('Sewanee: The University of the South', 'college-fair-2025');

        expect($registration->notes)->toContain('Jeff Heitzenrater will be joining me');
    });
});

describe('which spellings are one organization', function () {
    it('folds spellings that normalize together', function () {
        // "Rhodes College" three times and "RHODES COLLEGE" once.
        expect(Organization::query()->matchingName('Rhodes College')->count())->toBe(1)
            ->and(Organization::query()->matchingName('Rhodes College')->value('name'))->toBe('Rhodes College');
    });

    it('folds an abbreviation, a truncation and a typo onto the real name', function (string $submitted, string $seeded) {
        // Normalizing cannot see these — "uah" and "university of alabama in
        // huntsville" are different strings — so CANONICAL_NAMES carries them,
        // and each was confirmed by the submissions sharing an email domain.
        expect(Organization::query()->matchingName($submitted)->exists())->toBeFalse()
            ->and(Organization::query()->matchingName($seeded)->count())->toBe(1);
    })->with([
        ['UAH', 'University of Alabama in Huntsville'],
        ['Rh', 'Rhodes College'],
        ['Valdosta State Univer', 'Valdosta State University'],
        ['Middle Tennessee State Unviersity', 'Middle Tennessee State University'],
        ['Chattanooga State', 'Chattanooga State Community College'],
        ['SCAD (Savannah College of Art and Design)', 'Savannah College of Art and Design'],
        ['University of the South', 'Sewanee: The University of the South'],
    ]);

    it('keeps two organizations apart when only their names look alike', function () {
        // miamioh.edu and miami.edu, both at the 2026 fair.
        expect(Organization::query()->matchingName('Miami University')->value('name'))->toBe('Miami University')
            ->and(Organization::query()->matchingName('University of Miami')->exists())->toBeTrue();
    });

    it('does not fold a university into its own colleges', function () {
        // Four spellings share tntech.edu, and in 2024 the university and its
        // College of Education each registered. Folding them by email domain
        // would have deleted a real registration.
        $fair = Event::query()->where('slug', 'college-fair-2024')->sole();

        expect(Registration::query()->where('event_id', $fair->id)
            ->whereHas('organization', fn ($query) => $query->whereIn('name', [
                'Tennessee Tech University',
                'TN Tech College of Education',
            ]))->count())->toBe(2);
    });

    it('leaves the seeded organizations free of exact duplicates', function () {
        $collisions = Organization::query()
            ->selectRaw('normalized_name, count(*) as total')
            ->groupBy('normalized_name')
            ->havingRaw('count(*) > 1')
            ->get();

        // Near-duplicates the export cannot resolve are left for the admin
        // merge action; two rows the app itself would call the same are a bug.
        expect($collisions)->toBeEmpty();
    });
});

describe('what the export cannot say', function () {
    it('creates no user accounts, because nobody in the export ever signed up', function () {
        expect(User::query()->count())->toBe(0)
            ->and(Registration::query()->whereNotNull('user_id')->count())->toBe(0);
    });

    it('leaves every organization reachable by a campaign', function () {
        // AudienceBuilder drops an organization with no active rep and no
        // admissions_email (doc 07 §2 rule 1). None of these has an account, so
        // a null here would seed 158 organizations no win-back list can reach.
        expect(Organization::query()->whereNull('admissions_email')->count())->toBe(0);
    });

    it('guesses no website or address', function () {
        expect(Organization::query()->whereNotNull('website')->count())->toBe(0)
            ->and(Organization::query()->whereNotNull('address_line1')->count())->toBe(0)
            ->and(Organization::query()->whereNotNull('city')->count())->toBe(0);
    });

    it('records every row as confirmed, paid by check, at the fair list price', function () {
        $fair = Event::query()->where('slug', 'college-fair-2025')->sole();
        $roster = $fair->registrations;

        expect($roster->every(fn (Registration $r): bool => $r->status === RegistrationStatus::Confirmed))->toBeTrue()
            ->and($roster->every(fn (Registration $r): bool => $r->payment_method === PaymentMethod::Check))->toBeTrue()
            ->and($roster->every(fn (Registration $r): bool => $r->price_cents === $fair->price_cents))->toBeTrue()
            ->and($roster->every(fn (Registration $r): bool => $r->show_on_roster))->toBeTrue();
    });

    it('says on every row where it came from', function () {
        expect(Registration::query()
            ->where('notes', 'not like', 'Imported from the previous system%')
            ->count())->toBe(0);
    });

    it('confirms each registration on the day it was submitted', function () {
        $registration = registrationFor('Clemson University', 'college-fair-2023');

        expect($registration->confirmed_at->toDateString())->toBe('2022-12-02')
            ->and($registration->created_at->toDateString())->toBe('2022-12-02');
    });

    it('takes no payments, because the old form collected none', function () {
        expect(Registration::query()->has('payments')->count())->toBe(0);
    });
});

describe('re-running', function () {
    it('creates nothing the second time', function () {
        $this->seed(OrganizationSeeder::class);
        $this->seed(RegistrationSeeder::class);

        expect(Organization::query()->count())->toBe(158)
            ->and(Registration::query()->count())->toBe(354);
    });

    it('does not talk over a correction the coordinator has made', function () {
        $organization = Organization::query()->matchingName('Clemson University')->sole();
        $organization->update(['admissions_email' => 'admissions@clemson.edu']);

        registrationFor('Clemson University', 'college-fair-2023')->update([
            'status' => RegistrationStatus::Cancelled,
            'notes' => 'Cancelled by phone.',
        ]);

        $this->seed(OrganizationSeeder::class);
        $this->seed(RegistrationSeeder::class);

        expect($organization->fresh()->admissions_email)->toBe('admissions@clemson.edu')
            ->and(registrationFor('Clemson University', 'college-fair-2023'))
            ->status->toBe(RegistrationStatus::Cancelled)
            ->notes->toBe('Cancelled by phone.');
    });

    it('fills the gaps on an organization that already existed without contact details', function () {
        Organization::query()->matchingName('Clemson University')->sole()
            ->forceFill(['admissions_email' => null, 'admissions_phone' => null])->save();

        $this->seed(OrganizationSeeder::class);

        expect(Organization::query()->matchingName('Clemson University')->value('admissions_email'))
            ->not->toBeNull();
    });

    it('registers an organization for a fair it is missing, without touching the rest', function () {
        registrationFor('Clemson University', 'college-fair-2023')->delete();

        $this->seed(RegistrationSeeder::class);

        expect(Registration::query()->count())->toBe(354);
    });
});

/**
 * The one registration a named organization holds at a named fair.
 */
function registrationFor(string $organization, string $slug): Registration
{
    return Registration::query()
        ->where('event_id', Event::query()->where('slug', $slug)->value('id'))
        ->where('organization_id', Organization::query()->matchingName($organization)->value('id'))
        ->sole();
}
