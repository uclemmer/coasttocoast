<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use Illuminate\Support\Collection;

/**
 * The 2023-2026 rosters from the previous system's participant export
 * (card 6.6) — 354 places at four fairs.
 *
 * The second half of `OrganizationSeeder`, and it needs that one to have run:
 * an organization it cannot find is reported and skipped rather than invented,
 * because an organization created here would have none of the contact details
 * the export's other submissions carry.
 *
 * ## What the export cannot tell us, and what is written instead
 *
 * The old site's form collected a name, an organization, an email, a phone and
 * an optional message. It took no money and recorded no attendance, so three
 * fields on every row are a stated convention rather than a fact, exactly as in
 * `fair:import-roster`:
 *
 *  - **`status` is Confirmed.** The export is the list of organizations that
 *    signed up for a fair that has since happened. Nothing distinguishes one
 *    that then failed to appear, so nothing pretends to.
 *  - **`payment_method` is Check.** The old site had no gateway; the fair was
 *    paid by check. `Stripe` would be the false one, and null means "made free
 *    by a grant", which none of these were.
 *  - **`price_cents` is the fair's list price**, and for 2023-2025 that price
 *    is itself a reconstruction — see `EventSeeder`. A registration's price is
 *    normally the snapshot of what was actually charged (N1); here it is the
 *    best available figure, and it is a record rather than an input.
 *
 * `user_id` is null on every row: nobody in the export has an account. The
 * `rep_name` / `rep_email` / `rep_phone` snapshot is what the contact columns
 * exist for, and it is the whole reason they are not derived from `users`.
 *
 * ## Duplicate submissions
 *
 * A fair and an organization make one registration. Where the export has
 * several submissions for the pair, the most recent one supplies the contact
 * details and the others are written into `notes` — a coordinator reading the
 * row can still see that two people signed the same organization up, which is
 * an ordinary thing that happens and worth knowing before the table is set.
 *
 * Idempotent: a registration that already exists for a fair and an organization
 * is left completely alone. Re-running is safe, and it will not talk over a
 * cancellation, a refund or a coordinator's correction.
 */
class RegistrationSeeder extends ParticipantExportSeeder
{
    public function run(): void
    {
        $events = Event::query()->get()->keyBy('slug');
        $organizations = Organization::query()->pluck('id', 'normalized_name');

        $created = 0;
        $existed = 0;
        $skipped = 0;

        foreach ($this->rosters() as $roster) {
            $event = $events[$roster['event_slug']] ?? null;
            $organizationId = $organizations[$roster['organization_key']] ?? null;

            if (! $event instanceof Event) {
                // Deliberately not created. Inventing a fair from an export row
                // would produce an event with no date, venue or price, which
                // the roster and every audience would then have to cope with.
                $this->command?->warn("No fair with slug '{$roster['event_slug']}' — skipped {$roster['organization_name']}.");
                $skipped++;

                continue;
            }

            if ($organizationId === null) {
                $this->command?->warn("No organization matching '{$roster['organization_name']}' — run OrganizationSeeder first.");
                $skipped++;

                continue;
            }

            $exists = Registration::query()
                ->where('event_id', $event->getKey())
                ->where('organization_id', $organizationId)
                ->exists();

            if ($exists) {
                $existed++;

                continue;
            }

            $latest = $roster['latest'];

            Registration::query()->create([
                'event_id' => $event->getKey(),
                'organization_id' => $organizationId,
                'user_id' => null,
                'grant_id' => null,
                'status' => RegistrationStatus::Confirmed,
                'payment_method' => PaymentMethod::Check,
                'price_cents' => $event->price_cents,
                'rep_name' => $latest['rep_name'],
                'rep_email' => $latest['rep_email'],
                'rep_phone' => $latest['rep_phone'],
                'show_on_roster' => true,
                'notes' => $this->notes($roster['submissions']),
                'confirmed_at' => $latest['submitted_at'],
                'created_at' => $latest['submitted_at'],
                'updated_at' => $latest['submitted_at'],
            ]);

            $created++;
        }

        $this->command?->info("Registrations: {$created} created, {$existed} already present, {$skipped} skipped.");
    }

    /**
     * One entry per (fair, organization), carrying every submission for the
     * pair oldest first and the latest one singled out.
     *
     * @return Collection<string, array{event_slug: string, organization_key: string, organization_name: string, latest: array<string, mixed>, submissions: Collection<int, array<string, mixed>>}>
     */
    protected function rosters(): Collection
    {
        return $this->submissions()
            ->groupBy(fn (array $submission): string => $submission['event_slug'].'|'.$submission['organization_key'])
            ->map(fn (Collection $submissions): array => [
                'event_slug' => $submissions->first()['event_slug'],
                'organization_key' => $submissions->first()['organization_key'],
                'organization_name' => $submissions->first()['organization_name'],
                'latest' => $submissions->last(),
                'submissions' => $submissions,
            ]);
    }

    /**
     * Provenance, whatever the organization wrote in the form's message box,
     * and anyone whose submission was set aside as a duplicate.
     *
     * A double-click by the same person is not worth a line; a colleague who
     * signed the same organization up is, and so is a message they left with it.
     *
     * @param  Collection<int, array<string, mixed>>  $submissions
     */
    protected function notes(Collection $submissions): string
    {
        $latest = $submissions->last();

        $lines = ["Imported from the previous system's participant export."];

        if (filled($latest['message'])) {
            $lines[] = $latest['message'];
        }

        $others = $submissions
            ->slice(0, -1)
            ->reject(fn (array $submission): bool => $submission['rep_email'] === $latest['rep_email'])
            ->unique('rep_email')
            ->map(function (array $submission): string {
                $line = "{$submission['rep_name']} <{$submission['rep_email']}>";

                return filled($submission['message']) ? "{$line} — {$submission['message']}" : $line;
            });

        if ($others->isNotEmpty()) {
            $lines[] = 'Also submitted for this fair by: '.$others->implode('; ');
        }

        return implode("\n\n", $lines);
    }
}
