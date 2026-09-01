<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Shared reading of the previous system's participant export (card 6.6).
 *
 * `OrganizationSeeder` and `RegistrationSeeder` are two halves of one import
 * and have to agree exactly on which submissions belong to which organization —
 * so the grouping lives here rather than being written twice and drifting.
 *
 * ## The file
 *
 * `storage/app/private/participants.json` is the owner's export, read verbatim
 * rather than massaged, so what the seeder reads is what the old site produced.
 * 381 submissions across the 2023, 2024, 2025 and 2026 fairs, each carrying the
 * fair it belongs to:
 *
 *     first, last, organization, email, phone, message, created_at, event.slug
 *
 * There is no payment, address, website or account data in it, because the old
 * site's form collected none. `OrganizationSeeder` and `RegistrationSeeder`
 * each say what they do about that rather than inventing it.
 *
 * **It is deliberately NOT in the repository** (owner, 2026-09-01). It is real
 * contact data for ~380 real people, `storage/app/private` is gitignored, and
 * keeping it there means the names and addresses of every admissions
 * representative who ever registered are not in anybody's clone.
 *
 * The cost is that these seeders cannot run where the file is not, and the
 * failure mode to guard against is a roster that seeds EMPTY — invisible until
 * a win-back campaign resolves to nobody, months later. So nothing here degrades
 * quietly: `export()` throws, `DatabaseSeeder` checks `available()` and says out
 * loud when it skips them, and the tests that assert the seeded roster skip with
 * the path in the message rather than passing on an empty database.
 *
 * ## Two submissions are not two registrations
 *
 * The export is a form log, not a roster. 381 submissions collapse to 354
 * places at a fair, because people submitted twice — a double-click, a
 * corrected email address, a colleague signing up for the same table. Rows are
 * grouped by (fair, organization) and the LATEST submission wins: it is the
 * last thing the organization told us. `RegistrationSeeder` writes the ones it
 * set aside into the notes rather than dropping them silently.
 *
 * ## Which spellings are one organization
 *
 * Grouping is by `Organization::normalizeName()`, the same function the
 * duplicate warning (R2.7) and `fair:import-roster` use — so "RHODES COLLEGE"
 * and "Rhodes College" are one organization here for the same reason they are
 * one in the application.
 *
 * `CANONICAL_NAMES` handles what normalizing cannot: an abbreviation ("UAH"), a
 * truncated form fill ("Rh", "Valdosta State Univer"), a typo carried across
 * three fairs ("Middle Tennessee State Unviersity"), a parenthetical, a campus
 * suffix. Every entry is evidenced by the submissions sharing an email domain,
 * and every one was checked not to merge two organizations that attended the
 * same fair separately.
 *
 * It deliberately does NOT merge a university with its own colleges. Tennessee
 * Tech submitted under four names on tntech.edu and Mississippi State under two
 * on msstate.edu, and in 2024 both Tennessee Tech University and its College of
 * Education registered for the same fair — folding those together would delete
 * a real registration. They stay separate, and the admin merge action is where
 * a human decides otherwise.
 */
abstract class ParticipantExportSeeder extends Seeder
{
    /**
     * Spellings that are the same organization but do not normalize together.
     *
     * Keyed by `Organization::normalizeName()` of the spelling as submitted,
     * valued with the name to write. See the class docblock for the evidence
     * behind each and for what is deliberately absent.
     */
    protected const CANONICAL_NAMES = [
        // Truncated form fills, both submitted by the address that also
        // submitted the full name: garciaj@rhodes.edu, alvillarreal@valdosta.edu.
        'rh' => 'Rhodes College',
        'valdosta state univer' => 'Valdosta State University',

        // A typo that outlived three fairs. The 2026 submission from the same
        // mtsu.edu domain spells it correctly.
        'middle tennessee state unviersity' => 'Middle Tennessee State University',

        // Abbreviations and parentheticals of a name already in the export.
        'uah' => 'University of Alabama in Huntsville',
        'scad savannah college of art and design' => 'Savannah College of Art and Design',
        'washington university in st louis washu' => 'Washington University in St. Louis',
        'chattanooga state' => 'Chattanooga State Community College',
        'union college ny' => 'Union College',
        'brenau university gainesville ga' => 'Brenau University',

        // Miami University (miamioh.edu), which submitted as "-Ohio" twice in
        // 2026. Not the University of Miami (miami.edu), which also attended
        // that fair and is a different organization.
        'miami university ohio' => 'Miami University',

        // Sewanee submitted under three names across four fairs, all from
        // sewanee.edu. The colon is the institution's own styling, and the
        // spelling the development fixtures already use.
        'sewanee the university of the south' => 'Sewanee: The University of the South',
        'university of the south' => 'Sewanee: The University of the South',

        // Both spellings appear twice, so frequency cannot decide it. The
        // spelled-out form is the institution's own.
        'missouri university of science technology' => 'Missouri University of Science and Technology',

        // "The" is part of this campus's official name and the majority
        // spelling in the export either way.
        'university of tennessee chattanooga' => 'The University of Tennessee at Chattanooga',
    ];

    /**
     * The decoded export, held so the two seeders and the two passes within
     * each of them read the file once.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    protected ?Collection $export = null;

    /**
     * Every submission, oldest first, with its organization resolved.
     *
     * @return Collection<int, array{event_slug: string, organization_key: string, organization_name: string, rep_name: string, rep_email: string, rep_phone: string|null, message: string|null, submitted_at: Carbon, export_id: int}>
     */
    protected function submissions(): Collection
    {
        $names = $this->canonicalNames();

        return $this->export()
            ->map(function (array $row) use ($names): array {
                $key = $this->groupingKey($row['organization']);

                return [
                    'event_slug' => $row['event']['slug'],
                    'organization_key' => $key,
                    'organization_name' => $names[$key],
                    'rep_name' => trim($row['first'].' '.$row['last']),
                    'rep_email' => trim($row['email']),
                    'rep_phone' => $this->phone($row['phone']),
                    'message' => filled($row['message']) ? trim((string) $row['message']) : null,
                    'submitted_at' => Carbon::parse($row['created_at']),
                    'export_id' => (int) $row['id'],
                ];
            })
            // The export's own id breaks ties: some duplicate submissions share
            // a timestamp to the second, and "the latest wins" has to mean the
            // same thing on every run.
            ->sortBy(fn (array $row): array => [$row['submitted_at']->getTimestamp(), $row['export_id']])
            ->values();
    }

    /**
     * The name to write for each organization, keyed by its grouping key.
     *
     * An override in `CANONICAL_NAMES` decides it outright. Otherwise the most
     * frequently submitted spelling wins and a tie goes to the most recent one
     * — the organization's own latest word on how it writes its name.
     *
     * @return array<string, string>
     */
    protected function canonicalNames(): array
    {
        /** @var array<string, array<string, array{count: int, latest: string}>> $spellings */
        $spellings = [];

        foreach ($this->export() as $row) {
            $key = $this->groupingKey($row['organization']);
            $spellings[$key] ??= [];

            if (isset(self::CANONICAL_NAMES[Organization::normalizeName($row['organization'])])) {
                continue;
            }

            $spelling = trim($row['organization']);
            $spellings[$key][$spelling] ??= ['count' => 0, 'latest' => ''];
            $spellings[$key][$spelling]['count']++;
            $spellings[$key][$spelling]['latest'] = max($spellings[$key][$spelling]['latest'], $row['created_at']);
        }

        return collect($spellings)
            ->map(function (array $candidates, string $key): string {
                // Every submission for this organization was an override, so
                // the override is the only name it has.
                if ($candidates === []) {
                    return $this->overrideFor($key);
                }

                uasort($candidates, fn (array $a, array $b): int => [$b['count'], $b['latest']] <=> [$a['count'], $a['latest']]);

                return (string) array_key_first($candidates);
            })
            ->all();
    }

    /**
     * The key deciding which submissions are one organization: the normalized
     * form of the canonical name, so an override and the spelling it points at
     * land in the same group.
     */
    protected function groupingKey(string $submitted): string
    {
        $normalized = Organization::normalizeName($submitted);

        return Organization::normalizeName(self::CANONICAL_NAMES[$normalized] ?? $submitted);
    }

    /**
     * The override whose normalized value is this grouping key.
     */
    protected function overrideFor(string $key): string
    {
        foreach (self::CANONICAL_NAMES as $name) {
            if (Organization::normalizeName($name) === $key) {
                return $name;
            }
        }

        throw new RuntimeException("No canonical name for organization key '{$key}'.");
    }

    /**
     * Phone numbers are written exactly as they were typed.
     *
     * The application stores them that way everywhere else — there is no house
     * format and no normalizer — so imposing one here would make the seeded
     * rows unlike every row a rep has ever created. The only thing enforced is
     * the column's 20 characters; the export's longest is 17.
     */
    protected function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        return $phone !== '' && strlen($phone) <= 20 ? $phone : null;
    }

    /**
     * The owner's export. Gitignored, so it is present on the machine that was
     * given it and nowhere else — see the class docblock.
     */
    public static function path(): string
    {
        return storage_path('app/private/participants.json');
    }

    /**
     * Whether there is anything to seed from.
     *
     * `DatabaseSeeder` asks before calling, so a developer without the export
     * still gets a working development seed. Running either seeder BY NAME
     * without it is a different thing — you asked for the roster and it cannot
     * be produced — and that throws.
     */
    public static function available(): bool
    {
        return is_readable(static::path());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function export(): Collection
    {
        if ($this->export instanceof Collection) {
            return $this->export;
        }

        if (! static::available()) {
            // Loud, never a shrug. A roster that seeds empty is invisible until
            // a cross-year audience resolves to nobody.
            throw new RuntimeException('The participant export is missing: '.static::path());
        }

        return $this->export = collect(json_decode((string) file_get_contents(static::path()), true, 512, JSON_THROW_ON_ERROR));
    }
}
