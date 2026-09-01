<?php

use App\Models\Organization;
use App\Models\Registration;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\ParticipantExportSeeder;
use Database\Seeders\RegistrationSeeder;

/**
 * What happens on a machine that has not been given the participant export.
 *
 * The export is real contact data for ~380 people and is deliberately not in
 * the repository (owner, 2026-09-01), so this is the ordinary state of a fresh
 * clone and of CI — not an edge case.
 *
 * The failure to design against is not a crash. It is a roster that seeds
 * EMPTY: no error, no log line, and a win-back campaign resolving to nobody
 * months later. So each seeder is pointed at a path that does not exist and
 * asked to prove it says so.
 *
 * This lives apart from `ParticipantExportSeederTest` because that file skips
 * itself when the export is absent — which is every case this one is about.
 */
it('refuses to seed organizations from an export that is not there', function () {
    $seeder = new class extends OrganizationSeeder
    {
        public static function path(): string
        {
            return storage_path('app/private/no-such-participant-export.json');
        }
    };

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'no-such-participant-export.json')
        ->and(Organization::query()->count())->toBe(0);
});

it('refuses to seed registrations from an export that is not there', function () {
    $seeder = new class extends RegistrationSeeder
    {
        public static function path(): string
        {
            return storage_path('app/private/no-such-participant-export.json');
        }
    };

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'no-such-participant-export.json')
        ->and(Registration::query()->count())->toBe(0);
});

it('names the file it wanted, so the fix is obvious', function () {
    expect(ParticipantExportSeeder::path())->toEndWith('participants.json')
        // Under storage, which is gitignored. A path anywhere inside the repo
        // would mean the data had come back with it.
        ->and(ParticipantExportSeeder::path())->toContain('storage');
});

it('still gives a developer without the export a working development seed', function () {
    // The fixtures are what makes the app worth looking at locally, and they
    // must not be hostage to a file most machines will never have.
    $this->seed(DatabaseSeeder::class);

    expect(Organization::query()->count())->toBeGreaterThan(0)
        ->and(Registration::query()->count())->toBeGreaterThan(0);
})->skip(fn (): bool => ParticipantExportSeeder::available(), 'The export is present here, so this machine cannot exercise its absence.');
