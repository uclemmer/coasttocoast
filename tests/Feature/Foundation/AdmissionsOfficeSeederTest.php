<?php

use App\Models\Organization;
use App\Models\Registration;
use Database\Seeders\AdmissionsOfficeSeeder;
use Database\Seeders\ParticipantExportSeeder;
use Illuminate\Support\Str;

/**
 * The admissions office behind each organization (doc 19).
 *
 * These build their organizations with factories rather than seeding the
 * participant export, so the whole file runs on a machine that does not have
 * that export — which is every machine but the owner's (doc 18).
 */

/**
 * An organization exactly as `OrganizationSeeder` leaves it: a name, a
 * representative's own address copied up into the admissions columns, and
 * nothing else about the institution.
 */
function organizationFromTheExport(string $name, string $repEmail, string $repPhone = '(865) 555-0100'): Organization
{
    $organization = Organization::factory()->named($name)->create([
        'website' => null,
        'admissions_office' => null,
        'admissions_email' => $repEmail,
        'admissions_phone' => $repPhone,
        'address_line1' => null,
        'address_line2' => null,
        'city' => null,
        'state' => null,
        'postal_code' => null,
    ]);

    Registration::factory()->forOrganization($organization)->create([
        'rep_email' => $repEmail,
        'rep_phone' => $repPhone,
    ]);

    return $organization;
}

it('fills in the office behind an organization the export knew nothing about', function () {
    $organization = organizationFromTheExport('Clemson University', 'trgalbr@clemson.edu');

    $this->seed(AdmissionsOfficeSeeder::class);

    expect($organization->fresh())
        ->admissions_office->toBe('Office of Undergraduate Admissions')
        ->admissions_email->toBe('apply@admission.clemson.edu')
        ->admissions_phone->toBe('(864) 656-2287')
        ->city->toBe('Clemson')
        ->state->toBe('SC');
});

it('records the admissions office, not the university', function () {
    // The whole point of the exercise: a coordinator chasing a registration
    // wants the admissions page and the admissions mailroom, not the campus
    // switchboard and the institution's front door.
    organizationFromTheExport('Vanderbilt University', 'someone@vanderbilt.edu');

    $this->seed(AdmissionsOfficeSeeder::class);

    expect(Organization::query()->matchingName('Vanderbilt University')->value('website'))
        ->toBe('https://admissions.vanderbilt.edu/contact/');
});

it('matches on the normalized name, so a spelling difference still lands', function () {
    // The file says "The University of Tennessee at Chattanooga"; the fixtures
    // and the signup form both produce it without the article.
    organizationFromTheExport('University of Tennessee at Chattanooga', 'jay-freeman@utc.edu');

    $this->seed(AdmissionsOfficeSeeder::class);

    expect(Organization::query()->matchingName('utc')->exists())->toBeFalse()
        ->and(Organization::query()->matchingName('University of Tennessee at Chattanooga')->value('admissions_email'))
        ->toBe('admissions@utc.edu');
});

describe('replacing what the export put there', function () {
    it('swaps a representative’s own address for the office inbox', function () {
        $organization = organizationFromTheExport('Rhodes College', 'garciaj@rhodes.edu', '(901) 555-0142');

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh())
            ->admissions_email->toBe('adminfo@rhodes.edu')
            ->admissions_phone->toBe('(901) 843-3700');
    });

    it('leaves an address that is not one of this organization’s registration contacts', function () {
        // The narrow rule: only a value matching a rep contact on THIS
        // organization is the seeder's own earlier work. Anything else is
        // somebody's deliberate entry.
        $organization = organizationFromTheExport('Berry College', 'somebody@berry.edu');
        $organization->update(['admissions_email' => 'do-not-touch@berry.edu']);

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh()->admissions_email)->toBe('do-not-touch@berry.edu');
    });

    it('will not merge its address into half of somebody else’s', function () {
        // An address is one thing, not five columns. An organization carrying a
        // street and a city must not gain this office's "Fulford Hall" on
        // line 2 — the result is a third address that belongs to nobody.
        $organization = organizationFromTheExport('Sewanee: The University of the South', 'someone@sewanee.edu');
        $organization->update([
            'address_line1' => '735 University Avenue',
            'city' => 'Sewanee',
            'address_line2' => null,
            'postal_code' => null,
        ]);

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh())
            ->address_line2->toBeNull()
            ->postal_code->toBeNull();
    });

    it('fills the whole address onto an organization that has none', function () {
        $organization = organizationFromTheExport('Kenyon College', 'someone@kenyon.edu');

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh())
            ->address_line1->toBe('Lowell House')
            ->city->toBe('Gambier')
            ->state->toBe('OH')
            ->postal_code->toBe('43022-9623');
    });

    it('never overwrites a column that already says something', function () {
        $organization = organizationFromTheExport('Furman University', 'someone@furman.edu');
        $organization->update([
            'website' => 'https://furman.example/our-own-page',
            'city' => 'Somewhere Else',
        ]);

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh())
            ->website->toBe('https://furman.example/our-own-page')
            ->city->toBe('Somewhere Else');
    });

    it('replaces an address the export supplied even when no registration proves it', function () {
        // The gap the registration check alone leaves. `OrganizationSeeder`
        // takes an organization's address from its LATEST submission, and
        // `RegistrationSeeder` skips a fair the organization is already
        // registered for — so a fixture holding that year means the address is
        // real and nothing in the database says where it came from. Seven
        // organizations sat on a representative's personal address because of
        // it, with the published inbox available and unused.
        $organization = Organization::factory()->named('Rhodes College')->create([
            'admissions_email' => 'glovers@rhodes.edu',
            'website' => null,
        ]);

        // Deliberately a DIFFERENT address: the fixture that claimed the year.
        Registration::factory()->forOrganization($organization)->create([
            'rep_email' => 'someone.else@example.org',
        ]);

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh()->admissions_email)->toBe('adminfo@rhodes.edu');
    })->skip(
        fn (): bool => ! ParticipantExportSeeder::available(),
        'Needs the participant export, which is the source this rule consults.',
    );

    it('still refuses to touch an address nobody submitted', function () {
        // The other half: the rule got broader, not indiscriminate. A
        // coordinator's own entry is not in the export and is not replaced.
        $organization = Organization::factory()->named('Rhodes College')->create([
            'admissions_email' => 'ask-me-first@rhodes.edu',
        ]);

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh()->admissions_email)->toBe('ask-me-first@rhodes.edu');
    });

    it('fills a blank contact column even with no registration to compare against', function () {
        $organization = Organization::factory()->named('Skidmore College')->create([
            'admissions_email' => null,
            'admissions_phone' => null,
        ]);

        $this->seed(AdmissionsOfficeSeeder::class);

        expect($organization->fresh())
            ->admissions_email->toBe('admissions@skidmore.edu')
            ->admissions_phone->toBe('(518) 580-5570');
    });
});

it('leaves the logo alone, because fetching one is a separate command', function () {
    $organization = organizationFromTheExport('Wofford College', 'someone@wofford.edu');

    $this->seed(AdmissionsOfficeSeeder::class);

    expect($organization->fresh()->logo_path)->toBeNull();
});

it('touches nothing it has no entry for', function () {
    $organization = Organization::factory()->named('A College Nobody Researched')->create([
        'website' => null,
        'admissions_office' => null,
    ]);

    $this->seed(AdmissionsOfficeSeeder::class);

    expect($organization->fresh())
        ->website->toBeNull()
        ->admissions_office->toBeNull();
});

it('changes nothing the second time', function () {
    $organization = organizationFromTheExport('Rollins College', 'mvillamizar@rollins.edu');

    $this->seed(AdmissionsOfficeSeeder::class);
    $before = $organization->fresh()->updated_at;

    $this->seed(AdmissionsOfficeSeeder::class);

    expect($organization->fresh()->updated_at->eq($before))->toBeTrue();
});

describe('the researched data itself', function () {
    it('covers every organization with an office and a page', function () {
        $offices = admissionsOfficeData();

        expect($offices)->toHaveCount(156)
            ->and(collect($offices)->whereNull('admissions_office'))->toBeEmpty()
            ->and(collect($offices)->whereNull('website'))->toBeEmpty();
    });

    it('points every website at the admissions office rather than the front door', function () {
        // Not a spelling rule — a URL that is just the institution's homepage
        // is the failure this whole exercise exists to avoid. A handful of
        // small institutions genuinely publish admissions on their root page.
        $frontDoors = collect(admissionsOfficeData())
            ->filter(fn (array $office): bool => rtrim((string) $office['website'], '/') === rtrim((string) $office['logo_source'], '/'))
            ->keys();

        expect($frontDoors->count())->toBeLessThanOrEqual(3);
    });

    it('names no organization twice under a different spelling', function () {
        $normalized = collect(admissionsOfficeData())
            ->keys()
            ->map(fn (string $name): string => Organization::normalizeName($name));

        expect($normalized->duplicates())->toBeEmpty();
    });

    it('records a source for every logo, since the fetch command reads it', function () {
        expect(collect(admissionsOfficeData())->whereNull('logo_source'))->toBeEmpty();
    });

    it('holds no address that is obviously a named individual', function () {
        // A person is not an office and they move on, so several institutions
        // that publish only a director's address were left null instead.
        // A dotted local part is only a person when neither half is a role
        // word: "admissions.office@trincoll.edu" is an office, "j.smith@" is not.
        $roleWords = ['admission', 'admissions', 'office', 'apply', 'info', 'enroll', 'enrollment', 'recruit', 'recruitment', 'ugrad', 'undergrad', 'undergraduate', 'contact', 'welcome', 'go'];

        $suspicious = collect(admissionsOfficeData())
            ->pluck('admissions_email')
            ->filter()
            ->filter(function (string $email) use ($roleWords): bool {
                $local = Str::before($email, '@');

                return str_contains($local, '.')
                    && collect(explode('.', strtolower($local)))->intersect($roleWords)->isEmpty();
            });

        expect($suspicious)->toBeEmpty();
    });
});

/**
 * @return array<string, array<string, string|null>>
 */
function admissionsOfficeData(): array
{
    return json_decode(
        (string) file_get_contents(base_path('database/seeders/data/admissions-offices.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}
