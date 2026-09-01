<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The admissions office behind each organization on the roster.
 *
 * The participant export gave us a person — a representative's name, work
 * address and mobile — and nothing about the institution (doc 18). This fills
 * the rest in: which office handles admissions, its page, its own address,
 * its own phone number and its own inbox, for the 157 organizations that have
 * one.
 *
 * **It is the office, not the university.** `website` is the admissions page
 * rather than the institution's front door, and the address is the admissions
 * office's mailroom rather than the campus's street address, because that is
 * what a coordinator posting an invitation or chasing a registration needs.
 *
 * ## Where the data came from, and how far to trust it
 *
 * `database/seeders/data/admissions-offices.json`, researched from each
 * institution's own admissions and contact pages on 2026-09-01. It is public
 * institutional information — an office, a published inbox, a published number
 * — with no individual's details in it, which is why it is committed while the
 * participant export deliberately is not (doc 18).
 *
 * It was gathered by machine and **not verified one institution at a time**, so
 * treat it as a good starting point rather than as checked fact. Two rules kept
 * it honest and both cost coverage:
 *
 *  - **Nothing is derived.** A website is never guessed from an email domain
 *    (`mailbox.sc.edu` and `em.ufl.edu` both appear in the export and both
 *    would produce a plausible-looking wrong URL), and an inbox is never
 *    guessed from a pattern. A field nobody published is null.
 *  - **No individuals.** Several institutions publish only a named director's
 *    address for admissions. Those were left null rather than recorded: a
 *    person is not an office, and they move on.
 *
 * That leaves thirteen organizations with no `admissions_email` — mostly ones
 * routing enquiries through a web form (the Naval Academy, GWU) or obscuring
 * the address against scrapers. They show as gaps in `/staff/organizations`,
 * which is the right place for a human to close them.
 *
 * ## Replacing what the export put there
 *
 * `OrganizationSeeder` wrote a representative's own address into
 * `admissions_email` because that was the only address the export had, and
 * because an organization with no address at all is dropped from every campaign
 * (doc 07 §2 rule 1). A real office inbox is strictly better, so those get
 * replaced.
 *
 * The replacement is narrow on purpose. It happens only when the value sitting
 * there is one of that organization's own `registrations.rep_email` values —
 * that is exactly the fingerprint of `OrganizationSeeder` having copied it up,
 * it needs no provenance column, and it cannot match an address a coordinator
 * typed. Everything else is gap-filling: a column that already has something in
 * it is left alone.
 *
 * `logo_path` is deliberately untouched — see `fair:fetch-organization-logos`,
 * which is a separate command because it downloads files.
 *
 * Idempotent, and safe to run before or after `OrganizationSeeder`.
 */
class AdmissionsOfficeSeeder extends Seeder
{
    /**
     * The columns this seeder fills one at a time, and never overwrites.
     *
     * `admissions_email` and `admissions_phone` are handled separately: they
     * can also *replace* a value, under the narrow rule in the class docblock.
     * The address is handled separately too, because it is not a set of
     * independent columns — see `address()`.
     *
     * @var list<string>
     */
    protected const GAP_FILLED = [
        'admissions_office',
        'website',
    ];

    /**
     * The address, which moves as one thing.
     *
     * @var list<string>
     */
    protected const ADDRESS = [
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
    ];

    public function run(): void
    {
        $filled = 0;
        $replaced = 0;
        $unmatched = [];

        foreach ($this->offices() as $name => $office) {
            $organization = Organization::query()->matchingName($name)->first();

            if (! $organization instanceof Organization) {
                $unmatched[] = $name;

                continue;
            }

            $changes = [...$this->gaps($organization, $office), ...$this->address($organization, $office)];
            $contact = $this->contactUpgrades($organization, $office);

            if ($changes === [] && $contact === []) {
                continue;
            }

            $organization->forceFill([...$changes, ...$contact])->save();

            $filled += $changes !== [] ? 1 : 0;
            $replaced += $contact !== [] ? 1 : 0;
        }

        $this->command?->info("Admissions offices: {$filled} organizations filled in, {$replaced} contact details upgraded.");

        if ($unmatched !== []) {
            // Expected on a database that has not had OrganizationSeeder run,
            // and worth saying rather than swallowing.
            $this->command?->warn(count($unmatched).' organizations in the file are not in the database, including: '.implode(', ', array_slice($unmatched, 0, 3)).'.');
        }
    }

    /**
     * The columns that are empty and can simply be filled.
     *
     * @param  array<string, string|null>  $office
     * @return array<string, string>
     */
    protected function gaps(Organization $organization, array $office): array
    {
        $gaps = [];

        foreach (self::GAP_FILLED as $column) {
            if (blank($organization->{$column}) && filled($office[$column] ?? null)) {
                $gaps[$column] = $office[$column];
            }
        }

        return $gaps;
    }

    /**
     * The office's address, but only onto an organization that has none.
     *
     * An address is one thing, not five columns. Filling it field by field
     * merges two addresses into a third that belongs to nobody: an organization
     * carrying a street and a city gains this office's "Fulford Hall" on
     * `address_line2` and the result is undeliverable. Found in development,
     * where the fixtures invent addresses for organizations whose names are
     * real institutions — but the same thing happens to any organization whose
     * rep filled in half a profile.
     *
     * @param  array<string, string|null>  $office
     * @return array<string, string|null>
     */
    protected function address(Organization $organization, array $office): array
    {
        foreach (self::ADDRESS as $column) {
            if (filled($organization->{$column})) {
                return [];
            }
        }

        $address = [];

        foreach (self::ADDRESS as $column) {
            if (filled($office[$column] ?? null)) {
                $address[$column] = $office[$column];
            }
        }

        return $address;
    }

    /**
     * A representative's own contact details, replaced by the office's.
     *
     * Blank is filled. A value that matches one of this organization's own
     * registration contacts is replaced, because that is `OrganizationSeeder`'s
     * fingerprint. Anything else is somebody's deliberate entry and is left.
     *
     * @param  array<string, string|null>  $office
     * @return array<string, string>
     */
    protected function contactUpgrades(Organization $organization, array $office): array
    {
        $upgrades = [];

        foreach (['admissions_email' => 'rep_email', 'admissions_phone' => 'rep_phone'] as $column => $repColumn) {
            $offered = $office[$column] ?? null;

            if (blank($offered) || $organization->{$column} === $offered) {
                continue;
            }

            $fromTheExport = $organization->registrations()
                ->where($repColumn, $organization->{$column})
                ->exists();

            if (blank($organization->{$column}) || $fromTheExport) {
                $upgrades[$column] = $offered;
            }
        }

        return $upgrades;
    }

    /**
     * @return Collection<string, array<string, string|null>>
     */
    protected function offices(): Collection
    {
        $path = base_path('database/seeders/data/admissions-offices.json');

        if (! is_readable($path)) {
            throw new RuntimeException("The admissions office data is missing: {$path}");
        }

        return collect(json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }
}
