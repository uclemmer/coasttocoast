<?php

use App\Enums\Audience;
use App\Enums\RegistrationStatus;
use App\Models\Event as Fair;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\AudienceBuilder;

beforeEach(function () {
    $this->fair2025 = Fair::factory()->past(2)->create(['slug' => 'college-fair-2025', 'price_cents' => 19500]);
    $this->fair2026 = Fair::factory()->past(1)->create(['slug' => 'college-fair-2026', 'price_cents' => 21500]);

    $this->csv = tempnam(sys_get_temp_dir(), 'roster').'.csv';
});

afterEach(function () {
    @unlink($this->csv);
});

/**
 * @param  array<int, array<string, string>>  $rows
 */
function writeRoster(string $path, array $rows, ?array $header = null): void
{
    $header ??= [
        'organization_name', 'website', 'admissions_email', 'admissions_phone',
        'address_line1', 'address_line2', 'city', 'state', 'postal_code',
        'rep_name', 'rep_email', 'rep_phone', 'event_slug', 'price_cents', 'confirmed_on',
    ];

    $handle = fopen($path, 'wb');
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(fn (string $column): string => $row[$column] ?? '', $header));
    }

    fclose($handle);
}

describe('importing', function () {
    it('creates schools and confirmed registrations from a CSV', function () {
        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'website' => 'https://kenyon.example',
            'admissions_email' => 'admissions@kenyon.example',
            'city' => 'Gambier',
            'state' => 'OH',
            'rep_name' => 'Dana Whitfield',
            'rep_email' => 'dana@kenyon.example',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        $school = Organization::query()->where('name', 'Kenyon College')->firstOrFail();
        $registration = Registration::query()->where('organization_id', $school->id)->firstOrFail();

        expect($school->admissions_email)->toBe('admissions@kenyon.example')
            // Nobody in this application created it.
            ->and($school->created_by)->toBeNull()
            ->and($registration->status)->toBe(RegistrationStatus::Confirmed)
            ->and($registration->user_id)->toBeNull()
            ->and($registration->rep_email)->toBe('dana@kenyon.example')
            // Falls back to the fair's own price when the export has none.
            ->and($registration->price_cents)->toBe(21500);
    });

    it('matches an existing school by normalized name rather than duplicating it', function () {
        // "The Ohio State University" in a fifteen-year-old export should land
        // on "Ohio State University" already in the directory.
        $existing = Organization::factory()->named('Ohio State University')->create();

        writeRoster($this->csv, [[
            'organization_name' => 'The Ohio State University',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        expect(Organization::query()->count())->toBe(1)
            ->and($existing->registrations()->count())->toBe(1);
    });

    it('fills gaps in an existing profile but never overwrites it', function () {
        $existing = Organization::factory()->named('Kenyon College')->create([
            'website' => 'https://current.kenyon.example',
            'admissions_email' => null,
        ]);

        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'website' => 'https://ancient.kenyon.example',
            'admissions_email' => 'admissions@kenyon.example',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        expect($existing->refresh()->admissions_email)->toBe('admissions@kenyon.example')
            // Somebody entered this since. An import must not undo that.
            ->and($existing->website)->toBe('https://current.kenyon.example');
    });

    it('is re-runnable, so a corrected CSV can simply be run again', function () {
        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'rep_email' => 'wrong@kenyon.example',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'rep_email' => 'right@kenyon.example',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        expect(Registration::query()->count())->toBe(1)
            ->and(Registration::query()->first()->rep_email)->toBe('right@kenyon.example');
    });

    it('accepts a header a spreadsheet has capitalised and padded', function () {
        // A round-trip through Excel is the normal case, and failing on it
        // would be a pointless obstacle. Written by hand rather than through
        // the helper, because the point is the exact bytes of the header row.
        file_put_contents($this->csv, "  Organization_Name , Event_Slug\nKenyon College,college-fair-2026\n");

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        expect(Organization::query()->count())->toBe(1)
            ->and(Registration::query()->count())->toBe(1);
    });

    it('imports a row with nothing but a name and a fair', function () {
        // A partial record of a school that attended is worth far more than no
        // record.
        writeRoster($this->csv, [[
            'organization_name' => 'Sparse College',
            'event_slug' => 'college-fair-2025',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        expect(Registration::query()->count())->toBe(1)
            ->and(Registration::query()->first()->rep_name)->toBe('Sparse College');
    });
});

describe('refusals', function () {
    it('skips a row with no fair it recognises, rather than inventing one', function () {
        // A fair conjured from a spreadsheet cell would have no date, no venue
        // and no price, and the roster and audiences would have to cope.
        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'event_slug' => 'college-fair-1998',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])
            ->expectsOutputToContain("no fair with slug 'college-fair-1998'")
            ->assertSuccessful();

        expect(Fair::query()->count())->toBe(2)
            ->and(Registration::query()->count())->toBe(0);
    });

    it('skips a row missing the two things it cannot do without', function () {
        writeRoster($this->csv, [
            ['organization_name' => '', 'event_slug' => 'college-fair-2026'],
            ['organization_name' => 'Kenyon College', 'event_slug' => ''],
        ]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        expect(Organization::query()->count())->toBe(0);
    });

    it('fails cleanly on a file it cannot read', function () {
        $this->artisan('fair:import-roster', ['file' => 'nowhere.csv'])->assertFailed();
    });

    it('changes nothing on a dry run', function () {
        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        expect(Organization::query()->count())->toBe(0)
            ->and(Registration::query()->count())->toBe(0);
    });
});

describe('what the import is for', function () {
    it('makes the cross-year audiences resolve, which is the whole point', function () {
        // Without history there is no previous year, so LastEvent and
        // LapsedAnyPrevious — the win-back lists — resolve to nothing.
        $thisYear = Fair::factory()->published()->create(['slug' => 'college-fair-2027']);

        writeRoster($this->csv, [
            [
                'organization_name' => 'Lapsed College',
                'admissions_email' => 'admissions@lapsed.example',
                'event_slug' => 'college-fair-2026',
            ],
            [
                'organization_name' => 'Long Ago College',
                'admissions_email' => 'admissions@longago.example',
                'event_slug' => 'college-fair-2025',
            ],
        ]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        $builder = app(AudienceBuilder::class);

        // No accounts exist for imported schools, so every recipient is the
        // generic admissions-email fallback — which is exactly why that
        // fallback had to exist.
        expect($builder->resolve(Audience::LastEvent, $thisYear)->pluck('organizationName')->all())
            ->toBe(['Lapsed College'])
            ->and($builder->resolve(Audience::LapsedAnyPrevious, $thisYear)->pluck('organizationName')->sort()->values()->all())
            ->toBe(['Lapsed College', 'Long Ago College'])
            ->and($builder->resolve(Audience::LastEvent, $thisYear)->first()->generic)->toBeTrue();
    });

    it('lets a real rep account take over from the generic fallback', function () {
        $thisYear = Fair::factory()->published()->create(['slug' => 'college-fair-2027']);

        writeRoster($this->csv, [[
            'organization_name' => 'Kenyon College',
            'admissions_email' => 'admissions@kenyon.example',
            'event_slug' => 'college-fair-2026',
        ]]);

        $this->artisan('fair:import-roster', ['file' => $this->csv])->assertSuccessful();

        $school = Organization::query()->where('name', 'Kenyon College')->firstOrFail();
        $rep = User::factory()->rep($school)->create(['email' => 'dana@kenyon.example']);

        $recipients = app(AudienceBuilder::class)->resolve(Audience::LastEvent, $thisYear);

        expect($recipients->pluck('email')->all())->toBe([$rep->email])
            ->and($recipients->first()->generic)->toBeFalse();
    });
});
