<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * The 156 organizations that attended the 2023-2026 fairs, from the previous
 * system's participant export (card 6.6).
 *
 * Real history, not a fixture. Without it every cross-year audience is empty:
 * `LastEvent` and `LapsedAnyPrevious` — the win-back lists doc 07 exists for —
 * are set differences over the fair history, and there is no history until this
 * runs. `RegistrationSeeder` is the other half and needs these rows first.
 *
 * ## What the export gives an organization, and what it does not
 *
 * The old site's form asked for a person, not an institution, so there is no
 * website, address, admissions office or logo in it. Those columns are left
 * null rather than guessed. Deriving a website from an email domain was
 * considered and dropped — `mailbox.sc.edu` and `em.ufl.edu` are both in the
 * export, and both would produce a wrong URL that reads like a researched one.
 *
 * `admissions_email` and `admissions_phone` ARE filled, from the most recent
 * submission that organization made. This is a judgement call worth knowing
 * about: it is a representative's own work address, not an admissions office
 * inbox. It is filled because `AudienceBuilder` drops an organization with no
 * active rep and no `admissions_email` from every campaign (doc 07 §2 rule 1),
 * and none of these organizations has an account behind it — so leaving the
 * column null would seed 156 organizations that no campaign can ever reach,
 * which is the exact failure importing history is meant to prevent. The
 * coordinator can correct any of them in the admin panel.
 *
 * ## No user accounts
 *
 * Nobody in the export ever signed up for this application, so no `User` rows
 * are created for them — the same choice `fair:import-roster` makes, and the
 * reason `registrations.user_id` is nullable. Creating active rep accounts for
 * 380 real people would hand them logins they never asked for; creating retired
 * ones would leave the organizations unreachable anyway. They claim their
 * organization through signup (R2.7) when they come back.
 *
 * Idempotent, and conservative about it: an organization already present —
 * seeded, imported or created by a rep — is matched on its normalized name and
 * only has its EMPTY columns filled. Re-running never overwrites something a
 * human has since corrected.
 */
class OrganizationSeeder extends ParticipantExportSeeder
{
    public function run(): void
    {
        $created = 0;
        $filled = 0;

        foreach ($this->organizations() as $organization) {
            $existing = Organization::query()->matchingName($organization['name'])->first();

            if ($existing instanceof Organization) {
                $filled += $this->fillGaps($existing, $organization) ? 1 : 0;

                continue;
            }

            Organization::query()->create([
                'name' => $organization['name'],
                'admissions_email' => $organization['admissions_email'],
                'admissions_phone' => $organization['admissions_phone'],
                // Null: nobody in this application created it.
                'created_by' => null,
                // The real timeline, so "how long has this organization been
                // coming?" is answerable from the row itself.
                'created_at' => $organization['first_seen_at'],
                'updated_at' => $organization['last_seen_at'],
            ]);

            $created++;
        }

        $this->command?->info("Organizations: {$created} created, {$filled} filled in from the export.");
    }

    /**
     * One entry per organization, carrying the contact details from its most
     * recent submission and the span of its attendance.
     *
     * @return array<string, array{name: string, admissions_email: string, admissions_phone: string|null, first_seen_at: Carbon, last_seen_at: Carbon}>
     */
    protected function organizations(): array
    {
        $organizations = [];

        // `submissions()` is oldest first, so each pass overwrites the contact
        // details with a more recent one and the last write wins.
        foreach ($this->submissions() as $submission) {
            $key = $submission['organization_key'];

            $organizations[$key] = [
                'name' => $submission['organization_name'],
                'admissions_email' => $submission['rep_email'],
                'admissions_phone' => $submission['rep_phone'],
                'first_seen_at' => $organizations[$key]['first_seen_at'] ?? $submission['submitted_at'],
                'last_seen_at' => $submission['submitted_at'],
            ];
        }

        return $organizations;
    }

    /**
     * Fill only what is empty, and report whether anything changed.
     *
     * The development fixtures create some of these organizations by name
     * already, and a rep may have edited their own profile — an import must
     * never talk over either.
     *
     * @param  array{name: string, admissions_email: string, admissions_phone: string|null, first_seen_at: Carbon, last_seen_at: Carbon}  $organization
     */
    protected function fillGaps(Organization $existing, array $organization): bool
    {
        $gaps = [];

        foreach (['admissions_email', 'admissions_phone'] as $field) {
            if (blank($existing->{$field}) && filled($organization[$field])) {
                $gaps[$field] = $organization[$field];
            }
        }

        if ($gaps === []) {
            return false;
        }

        $existing->forceFill($gaps)->save();

        return true;
    }
}
