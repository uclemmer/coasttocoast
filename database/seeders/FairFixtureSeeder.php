<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventInterest;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The realistic development fixture: organizations, reps, three years of
 * registrations, grants in every status, and the awkward cases.
 *
 * DEVELOPMENT ONLY. It never runs from `ProductionSeeder`, and it opens the
 * 2027 fair for registration — something `EventSeeder` deliberately does not
 * do, because that event's date and price are placeholders (TODO-OWNER) and an
 * unpublished event cannot take money. Locally, a workable current fair beats
 * that caution; on a real host it does not.
 *
 * The shapes here exist because later cards need them, not for volume:
 *
 *  - organizations registered in 2025 and 2026 but NOT 2027 — the lapsed audiences
 *    are meaningless without them (doc 07 §2);
 *  - an organization with several reps, one pending and one retired — the membership
 *    gates (D9, R2.10);
 *  - an organization with no active rep at all but an admissions_email — the generic
 *    campaign fallback;
 *  - an organization with neither — the recipient that gets dropped with a log;
 *  - two organizations whose names normalize identically — the duplicate warning and
 *    the admin merge action (R2.7);
 *  - grants pending, approved free, approved percent-off, denied and revoked,
 *    with the approved ones actually applied to registrations, so the pricing
 *    snapshot is visible in real data.
 *
 * Idempotent: it does nothing at all if it has run before.
 */
class FairFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (Organization::query()->exists()) {
            $this->command?->warn('Fair fixtures already present — skipping.');

            return;
        }

        $fair2025 = Event::query()->where('slug', 'college-fair-2025')->firstOrFail();
        $fair2026 = Event::query()->where('slug', 'college-fair-2026')->firstOrFail();
        $fair2027 = $this->openTheCurrentFair();

        $veterans = $this->organizationsRegisteredEveryYear($fair2025, $fair2026, $fair2027);
        $this->lapsedOrganizations($fair2025, $fair2026);
        $this->organizationWithMessyMembership($fair2027);
        $this->organizationsWithNoActiveReps($fair2026);
        $this->duplicateNamedOrganizations();
        $this->grantsInEveryStatus($fair2027, $veterans);
        $this->awkwardRegistrations($fair2027);
        $this->interestList($fair2027);
    }

    /**
     * Publish and open the 2027 fair so the portal, the wizard and the admin
     * widgets have something live to work against. Development only — see the
     * class docblock.
     */
    protected function openTheCurrentFair(): Event
    {
        $fair = Event::query()->where('slug', 'college-fair-2027')->firstOrFail();

        $fair->update([
            'is_published' => true,
            'registration_opens_at' => Carbon::now()->subMonth(),
            'registration_closes_at' => Carbon::now()->addMonths(3),
        ]);

        return $fair;
    }

    /**
     * Six organizations that come back every year, each with one active rep. These
     * populate both rosters and the `AnyPreviousEvent` audience.
     *
     * @return array<int, Organization>
     */
    protected function organizationsRegisteredEveryYear(Event $fair2025, Event $fair2026, Event $fair2027): array
    {
        $names = [
            'Appalachian State University',
            'Belmont University',
            'Furman University',
            'Rhodes College',
            'Sewanee: The University of the South',
            'Vanderbilt University',
        ];

        $organizations = [];

        foreach ($names as $name) {
            $organization = $this->organization($name);
            $rep = User::factory()->rep($organization)->create();

            foreach ([$fair2025, $fair2026, $fair2027] as $fair) {
                $this->register($fair, $organization, $rep);
            }

            $organizations[] = $organization;
        }

        return $organizations;
    }

    /**
     * Organizations that attended a past fair and have not come back. Four stopped
     * after 2026 (the `LapsedLastEvent` set) and two after 2025 (in
     * `LapsedAnyPrevious` but not `LapsedLastEvent`) — the distinction the
     * audience truth table turns on.
     */
    protected function lapsedOrganizations(Event $fair2025, Event $fair2026): void
    {
        foreach (['Berry College', 'Emory University', 'Mercer University', 'Wofford College'] as $name) {
            $organization = $this->organization($name);
            $rep = User::factory()->rep($organization)->create();
            $this->register($fair2025, $organization, $rep);
            $this->register($fair2026, $organization, $rep);
        }

        foreach (['Hendrix College', 'Millsaps College'] as $name) {
            $organization = $this->organization($name);
            $rep = User::factory()->rep($organization)->create();
            $this->register($fair2025, $organization, $rep);
        }
    }

    /**
     * One organization carrying every membership state at once: an active rep who
     * did the registering, a claim waiting on the coordinator, and a
     * predecessor who has retired.
     */
    protected function organizationWithMessyMembership(Event $fair2027): void
    {
        $organization = $this->organization('University of Tennessee at Chattanooga');

        $active = User::factory()->rep($organization)->create(['name' => 'Dana Whitfield']);
        User::factory()->pendingRep($organization)->create(['name' => 'Priya Raman']);
        User::factory()->retiredRep($organization)->create(['name' => 'Harold Estes']);

        $this->register($fair2027, $organization, $active);
    }

    /**
     * The two campaign-fallback cases: an organization whose only rep has retired but
     * which has an admissions_email, and one that has neither. The first gets a
     * generic recipient; the second is dropped with a log (doc 07 §2 rule 1).
     */
    protected function organizationsWithNoActiveReps(Event $fair2026): void
    {
        // The one fixture organization that must carry a generic address for its
        // case to exist at all, so it is set here rather than left to
        // `AdmissionsOfficeSeeder` — this seeder has to hold up run on its own.
        // It is Maryville's real published inbox, not an invented one, so it
        // agrees with the researched data instead of blocking it; `SeederTest`
        // fails if the two ever drift apart.
        $reachable = $this->organization('Maryville College', [
            'admissions_email' => 'admissions@maryvillecollege.edu',
        ]);
        $retired = User::factory()->retiredRep($reachable)->create();
        $this->register($fair2026, $reachable, $retired);

        // `withoutAdmissionsEmail()` is redundant beside `organization()` and
        // kept anyway: this organization's null address is the fixture case,
        // not an accident of how it was built.
        $unreachable = Organization::factory()->named('Bryan College')
            ->withoutInstitutionalProfile()->withoutAdmissionsEmail()->create();
        $goneEntirely = User::factory()->retiredRep($unreachable)->create();
        $this->register($fair2026, $unreachable, $goneEntirely);
    }

    /**
     * Two organizations whose names normalize to the same string — the pair the
     * duplicate warning (R2.7) and the admin merge action operate on.
     */
    protected function duplicateNamedOrganizations(): void
    {
        $this->organization('The University of Example');
        $this->organization('University of Example');
    }

    /**
     * A grant in every status, with the approved ones actually applied so the
     * price snapshot is visible in real data rather than only in tests.
     *
     * @param  array<int, Organization>  $organizations
     */
    protected function grantsInEveryStatus(Event $fair2027, array $organizations): void
    {
        $coordinator = $this->coordinator();

        // Approved and free — the registration confirms with no payment at all.
        $freeOrganization = $this->organization('Southern Adventist University');
        $freeRep = User::factory()->rep($freeOrganization)->create();
        $freeGrant = Grant::factory()->free()->for($freeOrganization)->for($fair2027)
            ->create(['requested_by' => $freeRep->id, 'decided_by' => $coordinator?->id]);
        Registration::factory()->free()->forEvent($fair2027)->forOrganization($freeOrganization)->create([
            'user_id' => $freeRep->id,
            'grant_id' => $freeGrant->id,
            'rep_name' => $freeRep->name,
            'rep_email' => $freeRep->email,
        ]);

        // Approved at 50% off — applied to an existing registration so the
        // snapshot differs from the event's list price.
        $discounted = $organizations[0];
        $discountRep = $discounted->activeReps()->first();
        $percentGrant = Grant::factory()->percentOff(50)->for($discounted)->for($fair2027)
            ->create(['requested_by' => $discountRep?->id, 'decided_by' => $coordinator?->id]);
        $discounted->registrations()->where('event_id', $fair2027->id)->update([
            'grant_id' => $percentGrant->id,
            'price_cents' => $fair2027->priceFor($discounted),
        ]);

        // Pending — the coordinator's review queue has something in it.
        $pendingOrganization = $this->organization('Tennessee Wesleyan University');
        $pendingRep = User::factory()->rep($pendingOrganization)->create();
        Grant::factory()->for($pendingOrganization)->for($fair2027)->create(['requested_by' => $pendingRep->id]);

        // Denied, with a reason — the decision email needs one.
        $deniedOrganization = $this->organization('Lee University');
        $deniedRep = User::factory()->rep($deniedOrganization)->create();
        Grant::factory()->denied()->for($deniedOrganization)->for($fair2027)
            ->create(['requested_by' => $deniedRep->id, 'decided_by' => $coordinator?->id]);

        // Revoked while unused — revoking a used one is blocked, so this
        // organization has no registration against it.
        $revokedOrganization = $this->organization('Carson-Newman University');
        $revokedRep = User::factory()->rep($revokedOrganization)->create();
        Grant::factory()->revoked()->for($revokedOrganization)->for($fair2027)
            ->create(['requested_by' => $revokedRep->id, 'decided_by' => $coordinator?->id]);

        // Withdrawn — proves an organization can apply again after changing its mind.
        Grant::factory()->withdrawn()->for($pendingOrganization)->for($fair2027)
            ->create(['requested_by' => $pendingRep->id]);
    }

    /**
     * The registration states the admin panel has to cope with: checks in the
     * post, a cancellation, and an organization hidden from the public roster.
     */
    protected function awkwardRegistrations(Event $fair2027): void
    {
        foreach (['Union University', 'Bethel University'] as $name) {
            $organization = $this->organization($name);
            $rep = User::factory()->rep($organization)->create();

            Registration::factory()->pendingCheck()->forEvent($fair2027)->forOrganization($organization)->create([
                'user_id' => $rep->id,
                'price_cents' => $fair2027->price_cents,
                'rep_name' => $rep->name,
                'rep_email' => $rep->email,
            ]);
        }

        $cancelled = $this->organization('Trevecca Nazarene University');
        $cancelledRep = User::factory()->rep($cancelled)->create();
        Registration::factory()->cancelled()->forEvent($fair2027)->forOrganization($cancelled)->create([
            'user_id' => $cancelledRep->id,
            'price_cents' => $fair2027->price_cents,
            'rep_name' => $cancelledRep->name,
            'rep_email' => $cancelledRep->email,
            'notes' => 'Cancelled by the organization; travel budget cut.',
        ]);

        $hidden = $this->organization('Covenant College');
        $hiddenRep = User::factory()->rep($hidden)->create();
        $this->register($fair2027, $hidden, $hiddenRep)->update([
            'show_on_roster' => false,
            'notes' => 'Asked not to be listed publicly until they confirm staffing.',
        ]);

        // A coordinator's manual entry: no account behind it, only the snapshot.
        $manual = $this->organization('Dalton State College');
        Registration::factory()->manualEntry()->forEvent($fair2027)->forOrganization($manual)->create([
            'price_cents' => $fair2027->price_cents,
            'rep_name' => 'Kim Alvarado',
            'rep_email' => 'kalvarado@daltonstate.example',
            'notes' => 'Registered by phone; check received the same week.',
        ]);
    }

    /**
     * People waiting to be told when registration opens (R2.7), one of whom has
     * already been notified — so card 6.5's "only un-notified" rule has both
     * cases to work with.
     */
    protected function interestList(Event $fair2027): void
    {
        EventInterest::factory()->count(4)->for($fair2027)->create();
        EventInterest::factory()->notified()->for($fair2027)->create();
    }

    /**
     * A fixture organization: a real institution's name, and nothing invented
     * about the institution behind it.
     *
     * Every organization here is named after a real college, because a
     * development roster reading "Ferry-Bergnaum State University" is no use for
     * looking at. The factory's invented website, inbox, phone and address are
     * the problem — on a real name they are not placeholder data, they are
     * WRONG data, and because both real-data seeders only fill columns that are
     * empty, they also stop the researched values from ever landing. Eighteen of
     * these names are also in the participant export, so eighteen organizations
     * were showing `https://sawayn.com` where `AdmissionsOfficeSeeder` had the
     * real admissions page ready to write.
     *
     * Blank instead. `OrganizationSeeder` and `AdmissionsOfficeSeeder` fill them
     * in afterwards, and whatever neither covers stays visibly empty in
     * `/staff/organizations` — which is a gap a coordinator can close, rather
     * than a plausible-looking lie nobody thinks to check.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function organization(string $name, array $attributes = []): Organization
    {
        return Organization::factory()->named($name)->withoutInstitutionalProfile()->create($attributes);
    }

    /**
     * A confirmed, card-paid registration with the payment row to match, using
     * the rep's own details as the fair contact.
     */
    protected function register(Event $event, Organization $organization, User $rep): Registration
    {
        $registration = Registration::factory()->forEvent($event)->forOrganization($organization)->create([
            'user_id' => $rep->id,
            'price_cents' => $event->price_cents,
            'rep_name' => $rep->name,
            'rep_email' => $rep->email,
            'rep_phone' => $rep->phone,
            'confirmed_at' => (clone $event->starts_at)->subMonths(2),
        ]);

        Payment::factory()->for($registration)->create([
            'amount_cents' => $registration->price_cents,
        ]);

        return $registration;
    }

    /**
     * The seeded coordinator, who is recorded as the decider on every grant
     * decision in the fixtures.
     */
    protected function coordinator(): ?User
    {
        return User::query()->where('email', config('fair.coordinator.email'))->first();
    }
}
