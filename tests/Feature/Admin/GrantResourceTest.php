<?php

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Exceptions\GrantNotAllowed;
use App\Filament\Admin\Resources\GrantResource;
use App\Filament\Admin\Resources\GrantResource\Pages\ListGrants;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\GrantService;

beforeEach(function () {
    usingAdminPanel();
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->school = Organization::factory()->create();
});

describe('the review queue', function () {
    it('defaults to the applications waiting on a decision', function () {
        $pending = Grant::factory()->for($this->fair)->for($this->school)->create();
        $decided = Grant::factory()->free()->for($this->fair)->create();

        livewire(ListGrants::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$decided]);
    });

    it('counts the pending queue in the navigation, because somebody is waiting', function () {
        expect(GrantResource::getNavigationBadge())->toBeNull();

        Grant::factory()->count(3)->for($this->fair)->create();

        expect(GrantResource::getNavigationBadge())->toBe('3');
    });

    it('has no create or edit page at all', function () {
        // A grant is applied for, then decided. An edit form would let someone
        // set approved without a benefit, which priceFor() reads as no
        // discount — the school told it has a grant, then charged in full.
        expect(array_keys(GrantResource::getPages()))->toBe(['index', 'view']);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListGrants::class)->assertForbidden();
    });
});

describe('approving', function () {
    beforeEach(function () {
        $this->grant = Grant::factory()->for($this->fair)->for($this->school)->create();
    });

    it('approves a free grant and the school stops owing anything', function () {
        livewire(ListGrants::class)->callTableAction('approve', $this->grant, [
            'benefit_type' => GrantBenefit::Free->value,
        ]);

        expect($this->grant->refresh()->status)->toBe(GrantStatus::Approved)
            ->and($this->grant->decided_by)->toBe($this->coordinator->id)
            ->and($this->fair->priceFor($this->school))->toBe(0);
    });

    it('converts a custom price from dollars to cents', function () {
        livewire(ListGrants::class)->callTableAction('approve', $this->grant, [
            'benefit_type' => GrantBenefit::CustomPrice->value,
            'custom_price_dollars' => '50.00',
        ]);

        expect($this->grant->refresh()->custom_price_cents)->toBe(5000)
            ->and($this->fair->priceFor($this->school))->toBe(5000);
    });

    it('approves a percentage off', function () {
        livewire(ListGrants::class)->callTableAction('approve', $this->grant, [
            'benefit_type' => GrantBenefit::PercentOff->value,
            'percent_off' => 40,
        ]);

        expect($this->grant->refresh()->percent_off)->toBe(40)
            ->and($this->fair->priceFor($this->school))->toBe(12900);
    });

    it('requires the parameters the chosen benefit needs', function () {
        livewire(ListGrants::class)
            ->mountTableAction('approve', $this->grant)
            ->setTableActionData(['benefit_type' => GrantBenefit::PercentOff->value])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['percent_off']);

        expect($this->grant->refresh()->status)->toBe(GrantStatus::Pending);
    });

    it('disappears once the application has been decided', function () {
        $decided = Grant::factory()->free()->for($this->fair)->create();

        livewire(ListGrants::class)
            ->filterTable('status', GrantStatus::Approved->value)
            ->assertTableActionHidden('approve', $decided);
    });
});

describe('denying', function () {
    it('records the reason and leaves the price at list', function () {
        $grant = Grant::factory()->for($this->fair)->for($this->school)->create();

        livewire(ListGrants::class)->callTableAction('deny', $grant, [
            'reason' => 'Funds for this fair are already committed.',
        ]);

        expect($grant->refresh()->status)->toBe(GrantStatus::Denied)
            ->and($grant->denial_reason)->toBe('Funds for this fair are already committed.')
            ->and($this->fair->priceFor($this->school))->toBe(21500);
    });

    it('will not deny without a reason', function () {
        // "Denied", with nothing else, is how a school is lost for good.
        $grant = Grant::factory()->for($this->fair)->for($this->school)->create();

        livewire(ListGrants::class)
            ->mountTableAction('deny', $grant)
            ->setTableActionData(['reason' => null])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['reason']);

        expect($grant->refresh()->status)->toBe(GrantStatus::Pending);
    });
});

describe('revoking', function () {
    it('revokes an unused grant and restores list price', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();

        livewire(ListGrants::class)
            ->filterTable('status', GrantStatus::Approved->value)
            ->callTableAction('revoke', $grant, ['reason' => 'Awarded in error.']);

        expect($grant->refresh()->status)->toBe(GrantStatus::Revoked)
            ->and($this->fair->priceFor($this->school))->toBe(21500);
    });

    it('is hidden once a live registration is priced under it', function () {
        // The service refuses it anyway; an action that always fails is worse
        // than no action.
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();
        Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['grant_id' => $grant->id]);

        livewire(ListGrants::class)
            ->filterTable('status', GrantStatus::Approved->value)
            ->assertTableActionHidden('revoke', $grant);
    });

    it('comes back once the registration using it is cancelled', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();
        Registration::factory()->cancelled()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['grant_id' => $grant->id]);

        livewire(ListGrants::class)
            ->filterTable('status', GrantStatus::Approved->value)
            ->assertTableActionVisible('revoke', $grant);
    });
});

describe('service refusals', function () {
    it('shows the service message rather than a generic failure', function () {
        // The exception messages are written as user-facing copy, so there is
        // no second copy of the wording to drift.
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();
        Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['grant_id' => $grant->id]);

        // Reach past the visibility guard the way a stale browser tab would.
        expect(fn () => app(GrantService::class)->revoke($grant, $this->coordinator))
            ->toThrow(GrantNotAllowed::class, 'already been used');
    });
});
