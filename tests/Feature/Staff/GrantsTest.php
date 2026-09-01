<?php

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Livewire\Staff\Grants\Index as GrantIndex;
use App\Livewire\Staff\Grants\Show as ShowGrant;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 * The staff grant screens (docs/13).
 *
 * Ported from GrantResourceTest with their assertions intact — every one of
 * these checks a money outcome or a refusal, and neither changed. What changed
 * is how the decision is invoked: a Filament table action becomes a modal on
 * `Grants\Concerns\DecidesGrants`, shared by the queue and the detail screen.
 *
 * The Filament originals stay until app/Filament is deleted.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->organization = Organization::factory()->create();
});

describe('the review queue', function () {
    it('defaults to the applications waiting on a decision', function () {
        $pending = Grant::factory()->for($this->fair)->for($this->organization)->create();
        $decided = Grant::factory()->free()->for($this->fair)->create();

        $listed = livewire(GrantIndex::class)->instance()->grants();

        expect($listed->pluck('id')->all())->toBe([$pending->id])
            ->and($listed->pluck('id')->all())->not->toContain($decided->id);
    });

    it('counts the pending queue, because somebody is waiting', function () {
        // Filament put this in the sidebar as a navigation badge; it is on the
        // page now. The number is what mattered, not where it sat.
        expect(livewire(GrantIndex::class)->instance()->pendingCount())->toBe(0);

        Grant::factory()->count(3)->for($this->fair)->create();

        expect(livewire(GrantIndex::class)->instance()->pendingCount())->toBe(3);
    });

    it('has no create or edit screen at all', function () {
        // A grant is applied for, then decided. An edit form would let someone
        // set approved without a benefit, which priceFor() reads as no
        // discount — the organization told it has a grant, then charged in full.
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter(fn (?string $name): bool => $name !== null && str_starts_with($name, 'staff.grants'))
            ->values()
            ->all();

        expect($names)->toBe(['staff.grants', 'staff.grants.show']);
    });

    it('filters by fair and by status', function () {
        $other = Fair::factory()->create();
        $mine = Grant::factory()->for($this->fair)->for($this->organization)->create();
        Grant::factory()->for($other)->create();

        $page = livewire(GrantIndex::class);

        expect($page->set('eventId', (string) $this->fair->id)->instance()->grants()->pluck('id')->all())
            ->toBe([$mine->id]);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(GrantIndex::class)->assertForbidden();
    });
});

describe('approving', function () {
    beforeEach(function () {
        $this->grant = Grant::factory()->for($this->fair)->for($this->organization)->create();
    });

    it('approves a free grant and the organization stops owing anything', function () {
        livewire(GrantIndex::class)
            ->call('startApprove', $this->grant->id)
            ->set('benefitType', GrantBenefit::Free->value)
            ->call('approve');

        expect($this->grant->refresh()->status)->toBe(GrantStatus::Approved)
            ->and($this->grant->decided_by)->toBe($this->coordinator->id)
            ->and($this->fair->priceFor($this->organization))->toBe(0);
    });

    it('converts a custom price from dollars to cents', function () {
        // The conversion Filament did on the field. docs/13 records that moving
        // it once saved every fair at $0.
        livewire(GrantIndex::class)
            ->call('startApprove', $this->grant->id)
            ->set('benefitType', GrantBenefit::CustomPrice->value)
            ->set('customPriceDollars', '50.00')
            ->call('approve');

        expect($this->grant->refresh()->custom_price_cents)->toBe(5000)
            ->and($this->fair->priceFor($this->organization))->toBe(5000);
    });

    it('approves a percentage off', function () {
        livewire(GrantIndex::class)
            ->call('startApprove', $this->grant->id)
            ->set('benefitType', GrantBenefit::PercentOff->value)
            ->set('percentOff', '40')
            ->call('approve');

        expect($this->grant->refresh()->percent_off)->toBe(40)
            ->and($this->fair->priceFor($this->organization))->toBe(12900);
    });

    it('requires the parameters the chosen benefit needs', function () {
        livewire(GrantIndex::class)
            ->call('startApprove', $this->grant->id)
            ->set('benefitType', GrantBenefit::PercentOff->value)
            ->call('approve')
            ->assertHasErrors(['percentOff']);

        expect($this->grant->refresh()->status)->toBe(GrantStatus::Pending);
    });

    it('does not require an amount the chosen benefit has no use for', function () {
        // The other half of the rule: a free grant must not demand a price.
        livewire(GrantIndex::class)
            ->call('startApprove', $this->grant->id)
            ->set('benefitType', GrantBenefit::Free->value)
            ->call('approve')
            ->assertHasNoErrors();
    });

    it('is offered only while the application is undecided', function () {
        $decided = Grant::factory()->free()->for($this->fair)->create();

        $page = livewire(GrantIndex::class)->instance();

        expect($page->canDecide($this->grant))->toBeTrue()
            ->and($page->canDecide($decided))->toBeFalse();
    });
});

describe('denying', function () {
    it('records the reason and leaves the price at list', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->organization)->create();

        livewire(GrantIndex::class)
            ->call('startDeny', $grant->id)
            ->set('reason', 'Funds for this fair are already committed.')
            ->call('deny');

        expect($grant->refresh()->status)->toBe(GrantStatus::Denied)
            ->and($grant->denial_reason)->toBe('Funds for this fair are already committed.')
            ->and($this->fair->priceFor($this->organization))->toBe(21500);
    });

    it('will not deny without a reason', function () {
        // "Denied", with nothing else, is how an organization is lost for good.
        $grant = Grant::factory()->for($this->fair)->for($this->organization)->create();

        livewire(GrantIndex::class)
            ->call('startDeny', $grant->id)
            ->call('deny')
            ->assertHasErrors(['reason']);

        expect($grant->refresh()->status)->toBe(GrantStatus::Pending);
    });
});

describe('revoking', function () {
    it('revokes an unused grant and restores list price', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();

        livewire(GrantIndex::class)
            ->call('startRevoke', $grant->id)
            ->set('reason', 'Awarded in error.')
            ->call('revoke');

        expect($grant->refresh()->status)->toBe(GrantStatus::Revoked)
            ->and($this->fair->priceFor($this->organization))->toBe(21500);
    });

    it('is hidden once a live registration is priced under it', function () {
        // The service refuses it anyway; an action that always fails is worse
        // than no action.
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();
        Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['grant_id' => $grant->id]);

        expect(livewire(GrantIndex::class)->instance()->canRevoke($grant->refresh()))->toBeFalse();
    });

    it('comes back once the registration using it is cancelled', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();
        Registration::factory()->cancelled()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['grant_id' => $grant->id]);

        expect(livewire(GrantIndex::class)->instance()->canRevoke($grant->refresh()))->toBeTrue();
    });
});

describe('service refusals', function () {
    /*
     * Reaching past the hidden button the way a stale browser tab would. The
     * service's message is shown verbatim rather than replaced with something
     * generic, so the screen cannot drift from the rule — there is no second
     * copy of the wording.
     */
    it('shows the service message rather than a generic failure', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->organization)->create();
        Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->organization)
            ->create(['grant_id' => $grant->id]);

        livewire(GrantIndex::class)
            ->call('startRevoke', $grant->id)
            ->call('revoke')
            ->assertDispatched('ui-toast', function (string $event, array $params): bool {
                return $params['variant'] === 'danger'
                    && str_contains($params['message'], 'already been used');
            });

        expect($grant->refresh()->status)->toBe(GrantStatus::Approved);
    });
});

describe('the detail screen', function () {
    it('shows the justification, which is why the screen exists', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->organization)
            ->create(['justification' => 'Our entire senior class is on free lunch.']);

        livewire(ShowGrant::class, ['grant' => $grant])
            ->assertSuccessful()
            ->assertSee('Our entire senior class is on free lunch.');
    });

    it('decides from the detail screen too, and re-reads afterwards', function () {
        // The mounted copy is stale the moment the service returns; showing
        // "Pending" beside a toast saying "Approved" is how somebody clicks
        // twice.
        $grant = Grant::factory()->for($this->fair)->for($this->organization)->create();

        $page = livewire(ShowGrant::class, ['grant' => $grant])
            ->call('startApprove', $grant->id)
            ->set('benefitType', GrantBenefit::Free->value)
            ->call('approve');

        expect($page->instance()->record()->status)->toBe(GrantStatus::Approved);
    });

    it('keeps a user without the permission out', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->organization)->create();
        $this->actingAs(User::factory()->rep()->create());

        livewire(ShowGrant::class, ['grant' => $grant])->assertForbidden();
    });
});

describe('the per-decision authorization check', function () {
    /*
     * `GrantPolicy::update()` and `::viewAny()` are the same question today —
     * both are `grants.manage` — so no real user can pass mount() and fail the
     * check inside the decision. Removing that check leaves every other test in
     * this file green, which is precisely why it needs one of its own.
     *
     * It is not redundant code: it is where the decision actually lands, and
     * the day `update()` grows a condition (a grant already decided, a fair
     * closed, a coordinator scoped to one region) that check is what enforces
     * it. Denying the ability directly proves the line runs, independent of
     * what the policy currently says.
     */
    it('refuses a decision when update is denied, even though the page loaded', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->organization)->create();

        Gate::policy(Grant::class, GrantPolicyDenyingUpdate::class);

        livewire(GrantIndex::class)
            ->call('startApprove', $grant->id)
            ->set('benefitType', GrantBenefit::Free->value)
            ->call('approve')
            ->assertForbidden();

        expect($grant->refresh()->status)->toBe(GrantStatus::Pending);
    });
});

/** Lets the queue render, refuses the decision. */
class GrantPolicyDenyingUpdate
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Grant $grant): bool
    {
        return true;
    }

    public function update(User $user, Grant $grant): bool
    {
        return false;
    }
}
