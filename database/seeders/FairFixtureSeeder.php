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
 * The realistic development fixture: schools, reps, three years of
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
 *  - schools registered in 2025 and 2026 but NOT 2027 — the lapsed audiences
 *    are meaningless without them (doc 07 §2);
 *  - a school with several reps, one pending and one retired — the membership
 *    gates (D9, R2.10);
 *  - a school with no active rep at all but an admissions_email — the generic
 *    campaign fallback;
 *  - a school with neither — the recipient that gets dropped with a log;
 *  - two schools whose names normalize identically — the duplicate warning and
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

        $veterans = $this->schoolsRegisteredEveryYear($fair2025, $fair2026, $fair2027);
        $this->lapsedSchools($fair2025, $fair2026);
        $this->schoolWithMessyMembership($fair2027);
        $this->schoolsWithNoActiveReps($fair2026);
        $this->duplicateNamedSchools();
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
     * Six schools that come back every year, each with one active rep. These
     * populate both rosters and the `AnyPreviousEvent` audience.
     *
     * @return array<int, Organization>
     */
    protected function schoolsRegisteredEveryYear(Event $fair2025, Event $fair2026, Event $fair2027): array
    {
        $names = [
            'Appalachian State University',
            'Belmont University',
            'Furman University',
            'Rhodes College',
            'Sewanee: The University of the South',
            'Vanderbilt University',
        ];

        $schools = [];

        foreach ($names as $name) {
            $school = Organization::factory()->named($name)->create();
            $rep = User::factory()->rep($school)->create();

            foreach ([$fair2025, $fair2026, $fair2027] as $fair) {
                $this->register($fair, $school, $rep);
            }

            $schools[] = $school;
        }

        return $schools;
    }

    /**
     * Schools that attended a past fair and have not come back. Four stopped
     * after 2026 (the `LapsedLastEvent` set) and two after 2025 (in
     * `LapsedAnyPrevious` but not `LapsedLastEvent`) — the distinction the
     * audience truth table turns on.
     */
    protected function lapsedSchools(Event $fair2025, Event $fair2026): void
    {
        foreach (['Berry College', 'Emory University', 'Mercer University', 'Wofford College'] as $name) {
            $school = Organization::factory()->named($name)->create();
            $rep = User::factory()->rep($school)->create();
            $this->register($fair2025, $school, $rep);
            $this->register($fair2026, $school, $rep);
        }

        foreach (['Hendrix College', 'Millsaps College'] as $name) {
            $school = Organization::factory()->named($name)->create();
            $rep = User::factory()->rep($school)->create();
            $this->register($fair2025, $school, $rep);
        }
    }

    /**
     * One school carrying every membership state at once: an active rep who
     * did the registering, a claim waiting on the coordinator, and a
     * predecessor who has retired.
     */
    protected function schoolWithMessyMembership(Event $fair2027): void
    {
        $school = Organization::factory()->named('University of Tennessee at Chattanooga')->create();

        $active = User::factory()->rep($school)->create(['name' => 'Dana Whitfield']);
        User::factory()->pendingRep($school)->create(['name' => 'Priya Raman']);
        User::factory()->retiredRep($school)->create(['name' => 'Harold Estes']);

        $this->register($fair2027, $school, $active);
    }

    /**
     * The two campaign-fallback cases: a school whose only rep has retired but
     * which has an admissions_email, and one that has neither. The first gets a
     * generic recipient; the second is dropped with a log (doc 07 §2 rule 1).
     */
    protected function schoolsWithNoActiveReps(Event $fair2026): void
    {
        $reachable = Organization::factory()->named('Maryville College')->create([
            'admissions_email' => 'admissions@maryvillecollege.example',
        ]);
        $retired = User::factory()->retiredRep($reachable)->create();
        $this->register($fair2026, $reachable, $retired);

        $unreachable = Organization::factory()->named('Bryan College')->withoutAdmissionsEmail()->create();
        $goneEntirely = User::factory()->retiredRep($unreachable)->create();
        $this->register($fair2026, $unreachable, $goneEntirely);
    }

    /**
     * Two schools whose names normalize to the same string — the pair the
     * duplicate warning (R2.7) and the admin merge action operate on.
     */
    protected function duplicateNamedSchools(): void
    {
        Organization::factory()->named('The University of Example')->create();
        Organization::factory()->named('University of Example')->create();
    }

    /**
     * A grant in every status, with the approved ones actually applied so the
     * price snapshot is visible in real data rather than only in tests.
     *
     * @param  array<int, Organization>  $schools
     */
    protected function grantsInEveryStatus(Event $fair2027, array $schools): void
    {
        $coordinator = $this->coordinator();

        // Approved and free — the registration confirms with no payment at all.
        $freeSchool = Organization::factory()->named('Southern Adventist University')->create();
        $freeRep = User::factory()->rep($freeSchool)->create();
        $freeGrant = Grant::factory()->free()->for($freeSchool)->for($fair2027)
            ->create(['requested_by' => $freeRep->id, 'decided_by' => $coordinator?->id]);
        Registration::factory()->free()->forEvent($fair2027)->forOrganization($freeSchool)->create([
            'user_id' => $freeRep->id,
            'grant_id' => $freeGrant->id,
            'rep_name' => $freeRep->name,
            'rep_email' => $freeRep->email,
        ]);

        // Approved at 50% off — applied to an existing registration so the
        // snapshot differs from the event's list price.
        $discounted = $schools[0];
        $discountRep = $discounted->activeReps()->first();
        $percentGrant = Grant::factory()->percentOff(50)->for($discounted)->for($fair2027)
            ->create(['requested_by' => $discountRep?->id, 'decided_by' => $coordinator?->id]);
        $discounted->registrations()->where('event_id', $fair2027->id)->update([
            'grant_id' => $percentGrant->id,
            'price_cents' => $fair2027->priceFor($discounted),
        ]);

        // Pending — the coordinator's review queue has something in it.
        $pendingSchool = Organization::factory()->named('Tennessee Wesleyan University')->create();
        $pendingRep = User::factory()->rep($pendingSchool)->create();
        Grant::factory()->for($pendingSchool)->for($fair2027)->create(['requested_by' => $pendingRep->id]);

        // Denied, with a reason — the decision email needs one.
        $deniedSchool = Organization::factory()->named('Lee University')->create();
        $deniedRep = User::factory()->rep($deniedSchool)->create();
        Grant::factory()->denied()->for($deniedSchool)->for($fair2027)
            ->create(['requested_by' => $deniedRep->id, 'decided_by' => $coordinator?->id]);

        // Revoked while unused — revoking a used one is blocked, so this
        // school has no registration against it.
        $revokedSchool = Organization::factory()->named('Carson-Newman University')->create();
        $revokedRep = User::factory()->rep($revokedSchool)->create();
        Grant::factory()->revoked()->for($revokedSchool)->for($fair2027)
            ->create(['requested_by' => $revokedRep->id, 'decided_by' => $coordinator?->id]);

        // Withdrawn — proves a school can apply again after changing its mind.
        Grant::factory()->withdrawn()->for($pendingSchool)->for($fair2027)
            ->create(['requested_by' => $pendingRep->id]);
    }

    /**
     * The registration states the admin panel has to cope with: checks in the
     * post, a cancellation, and a school hidden from the public roster.
     */
    protected function awkwardRegistrations(Event $fair2027): void
    {
        foreach (['Union University', 'Bethel University'] as $name) {
            $school = Organization::factory()->named($name)->create();
            $rep = User::factory()->rep($school)->create();

            Registration::factory()->pendingCheck()->forEvent($fair2027)->forOrganization($school)->create([
                'user_id' => $rep->id,
                'price_cents' => $fair2027->price_cents,
                'rep_name' => $rep->name,
                'rep_email' => $rep->email,
            ]);
        }

        $cancelled = Organization::factory()->named('Trevecca Nazarene University')->create();
        $cancelledRep = User::factory()->rep($cancelled)->create();
        Registration::factory()->cancelled()->forEvent($fair2027)->forOrganization($cancelled)->create([
            'user_id' => $cancelledRep->id,
            'price_cents' => $fair2027->price_cents,
            'rep_name' => $cancelledRep->name,
            'rep_email' => $cancelledRep->email,
            'notes' => 'Cancelled by the school; travel budget cut.',
        ]);

        $hidden = Organization::factory()->named('Covenant College')->create();
        $hiddenRep = User::factory()->rep($hidden)->create();
        $this->register($fair2027, $hidden, $hiddenRep)->update([
            'show_on_roster' => false,
            'notes' => 'Asked not to be listed publicly until they confirm staffing.',
        ]);

        // A coordinator's manual entry: no account behind it, only the snapshot.
        $manual = Organization::factory()->named('Dalton State College')->create();
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
     * A confirmed, card-paid registration with the payment row to match, using
     * the rep's own details as the fair contact.
     */
    protected function register(Event $event, Organization $school, User $rep): Registration
    {
        $registration = Registration::factory()->forEvent($event)->forOrganization($school)->create([
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
