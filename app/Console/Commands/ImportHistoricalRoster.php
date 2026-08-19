<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Brings the 2025 and 2026 rosters in from the old site (card 6.6).
 *
 * Why it matters: every cross-year audience is only as good as the history
 * behind it. Without this, the first year on the new system has no previous
 * year, so `LastEvent` and `LapsedAnyPrevious` — the win-back lists, the whole
 * reason doc 07 exists — resolve to nothing.
 *
 * **The owner has no export file yet** (standing answer A3, doc 10). This is
 * built against a documented CSV schema, and the schema is the deliverable as
 * much as the code: whatever ISPEUS can produce needs massaging into these
 * columns, and then this runs.
 *
 *     organization_name,website,admissions_email,admissions_phone,
 *     address_line1,address_line2,city,state,postal_code,
 *     rep_name,rep_email,rep_phone,event_slug,price_cents,confirmed_on
 *
 * Only `organization_name` and `event_slug` are required. Everything else is
 * optional, because a fifteen-year-old export will be missing things and a
 * partial record of a school that attended is worth far more than no record.
 *
 * Idempotent by (event, organization): re-running updates rather than
 * duplicating, so the owner can fix the CSV and run it again.
 */
class ImportHistoricalRoster extends Command
{
    protected $signature = 'fair:import-roster
        {file : Path to the CSV}
        {--dry-run : Report what would happen and change nothing}';

    protected $description = 'Import past years\' rosters so cross-year campaign audiences work from day one.';

    /** @var array<string, int> */
    protected array $tally = [
        'schools created' => 0,
        'schools matched' => 0,
        'registrations created' => 0,
        'registrations updated' => 0,
        'rows skipped' => 0,
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — nothing will be written.');
        }

        foreach ($this->rows($path) as $line => $row) {
            $this->importRow($row, $line, $dryRun);
        }

        $this->newLine();
        foreach ($this->tally as $label => $count) {
            $this->line(sprintf('%-24s %d', $label, $count));
        }

        return self::SUCCESS;
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    protected function rows(string $path): iterable
    {
        $handle = fopen($path, 'rb');
        $header = null;
        $line = 1;

        while (($record = fgetcsv($handle)) !== false) {
            if ($header === null) {
                // Lowercased and trimmed, because a spreadsheet round-trip
                // capitalises things and adds spaces, and failing on that
                // would be a pointless obstacle.
                $header = array_map(fn (?string $column): string => Str::of((string) $column)->trim()->lower()->value(), $record);

                continue;
            }

            $line++;

            if ($record === [null] || $record === []) {
                continue;
            }

            yield $line => array_combine(
                $header,
                array_pad(array_map(fn (?string $value): string => trim((string) $value), $record), count($header), ''),
            );
        }

        fclose($handle);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function importRow(array $row, int $line, bool $dryRun): void
    {
        $name = $row['organization_name'] ?? '';
        $slug = $row['event_slug'] ?? '';

        if (blank($name) || blank($slug)) {
            $this->warn("Line {$line}: needs organization_name and event_slug. Skipped.");
            $this->tally['rows skipped']++;

            return;
        }

        $event = Event::query()->where('slug', $slug)->first();

        if (! $event instanceof Event) {
            // Deliberately not created. Inventing a fair from a spreadsheet
            // cell would produce an event with no date, no venue and no price,
            // which the roster and the audiences would then have to cope with.
            $this->warn("Line {$line}: no fair with slug '{$slug}'. Skipped.");
            $this->tally['rows skipped']++;

            return;
        }

        $organization = $this->matchOrCreate($name, $row, $dryRun);

        if ($organization === null) {
            return;
        }

        $this->recordAttendance($event, $organization, $row, $dryRun);
    }

    /**
     * Match on the normalized name, so "The Ohio State University" in the
     * export lands on "Ohio State University" already in the directory.
     *
     * @param  array<string, string>  $row
     */
    protected function matchOrCreate(string $name, array $row, bool $dryRun): ?Organization
    {
        $existing = Organization::query()->matchingName($name)->first();

        if ($existing instanceof Organization) {
            $this->tally['schools matched']++;

            if (! $dryRun) {
                // Fills gaps only. An import must never overwrite a profile
                // somebody has since edited by hand.
                $this->fillGaps($existing, $row);
            }

            return $existing;
        }

        $this->tally['schools created']++;

        if ($dryRun) {
            return null;
        }

        return Organization::query()->create([
            'name' => $name,
            'website' => $row['website'] ?? null,
            'admissions_email' => $row['admissions_email'] ?? null,
            'admissions_phone' => $row['admissions_phone'] ?? null,
            'address_line1' => $row['address_line1'] ?? null,
            'address_line2' => $row['address_line2'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'postal_code' => $row['postal_code'] ?? null,
            // Null: nobody in this application created it.
            'created_by' => null,
        ]);
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function fillGaps(Organization $organization, array $row): void
    {
        $gaps = [];

        foreach (['website', 'admissions_email', 'admissions_phone', 'address_line1', 'address_line2', 'city', 'state', 'postal_code'] as $field) {
            if (blank($organization->{$field}) && filled($row[$field] ?? null)) {
                $gaps[$field] = $row[$field];
            }
        }

        if ($gaps !== []) {
            $organization->forceFill($gaps)->save();
        }
    }

    /**
     * A confirmed registration with no account behind it, exactly like a
     * coordinator's manual entry.
     *
     * Goes through the model rather than `RegistrationService` on purpose:
     * the service's rules are about *taking* a registration — the window is
     * open, the rep is active, the price comes from the current grant — and
     * none of them apply to recording something that happened in 2025.
     *
     * @param  array<string, string>  $row
     */
    protected function recordAttendance(Event $event, Organization $organization, array $row, bool $dryRun): void
    {
        $existing = Registration::query()
            ->where('event_id', $event->getKey())
            ->where('organization_id', $organization->getKey())
            ->first();

        // `?? null` on every lookup, not `?:`. A CSV carrying only the two
        // required columns is explicitly supported, and a missing key would
        // otherwise take the whole import down on row one.
        $attributes = [
            'status' => RegistrationStatus::Confirmed,
            'payment_method' => PaymentMethod::Check,
            'price_cents' => filled($row['price_cents'] ?? null)
                ? (int) $row['price_cents']
                : $event->price_cents,
            // The school's own name is a better placeholder than nothing: the
            // roster reads correctly and the coordinator can see at a glance
            // which rows still need a person attached.
            'rep_name' => ($row['rep_name'] ?? null) ?: $organization->name,
            'rep_email' => ($row['rep_email'] ?? null)
                ?: ($organization->admissions_email ?: 'unknown@example.invalid'),
            'rep_phone' => ($row['rep_phone'] ?? null) ?: null,
            'show_on_roster' => true,
            'notes' => 'Imported from the previous system.',
            'confirmed_at' => filled($row['confirmed_on'] ?? null)
                ? Carbon::parse($row['confirmed_on'])
                : $event->starts_at,
        ];

        if ($dryRun) {
            $this->tally[$existing ? 'registrations updated' : 'registrations created']++;

            return;
        }

        if ($existing instanceof Registration) {
            // Re-runnable: the owner fixes a column in the CSV and runs it
            // again rather than unpicking rows by hand.
            $existing->forceFill($attributes)->save();
            $this->tally['registrations updated']++;

            return;
        }

        Registration::query()->create([
            'event_id' => $event->getKey(),
            'organization_id' => $organization->getKey(),
            'user_id' => null,
            'grant_id' => null,
            ...$attributes,
        ]);

        $this->tally['registrations created']++;
    }
}
