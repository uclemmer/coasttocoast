<?php

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Events\GrantApplied;
use App\Events\GrantApproved;
use App\Events\GrantDenied;
use App\Events\GrantRevoked;
use App\Events\GrantWithdrawn;
use App\Exceptions\GrantNotAllowed;
use App\Models\Event as Fair;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Services\GrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(GrantService::class);
    $this->fair = Fair::factory()->registrationOpen()->priced(21500)->create();
    $this->school = Organization::factory()->create();
    $this->rep = User::factory()->rep($this->school)->create();
    $this->coordinator = User::factory()->coordinator()->create();
});

describe('applying', function () {
    it('records an application from an active rep', function () {
        $grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Our travel budget was cut.');

        expect($grant->status)->toBe(GrantStatus::Pending)
            ->and($grant->requested_by)->toBe($this->rep->id)
            ->and($grant->justification)->toBe('Our travel budget was cut.')
            // The applicant names no figure. The coordinator decides what the
            // grant is worth, so nothing is set here.
            ->and($grant->benefit_type)->toBeNull()
            ->and($grant->decided_at)->toBeNull();
    });

    it('fires an event so the coordinator can be told', function () {
        Event::fake([GrantApplied::class]);

        $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');

        Event::assertDispatched(GrantApplied::class);
    });

    it('refuses a pending or retired rep', function (string $state) {
        $rep = User::factory()->{$state}($this->school)->create();

        expect(fn () => $this->service->apply($this->fair, $this->school, $rep, 'Please.'))
            ->toThrow(GrantNotAllowed::class, 'approved representative');
    })->with(['pendingRep', 'retiredRep']);

    it('refuses a rep applying for a school that is not theirs', function () {
        $other = Organization::factory()->create();

        expect(fn () => $this->service->apply($this->fair, $other, $this->rep, 'Please.'))
            ->toThrow(GrantNotAllowed::class, 'the school your account belongs to');
    });

    it('allows an application before registration opens', function () {
        // Lining funding up before registration opens is the point of applying.
        $upcoming = Fair::factory()->registrationNotYetOpen()->create();

        expect($this->service->apply($upcoming, $this->school, $this->rep, 'Please.'))
            ->status->toBe(GrantStatus::Pending);
    });

    it('refuses an application for a fair that has already happened', function () {
        $past = Fair::factory()->past()->create();

        expect(fn () => $this->service->apply($past, $this->school, $this->rep, 'Please.'))
            ->toThrow(GrantNotAllowed::class, 'have closed');
    });

    it('refuses a second application while one is live', function (string $state) {
        Grant::factory()->{$state}()->for($this->fair)->for($this->school)->create();

        expect(fn () => $this->service->apply($this->fair, $this->school, $this->rep, 'Please.'))
            ->toThrow(GrantNotAllowed::class, 'already applied');
    })->with([
        'pending' => 'pending',
        'approved' => 'free',
        // A denial is final for that fair — reapplying is not a way around it.
        'denied' => 'denied',
        'revoked' => 'revoked',
    ]);

    it('lets a school apply again after withdrawing', function () {
        Grant::factory()->withdrawn()->for($this->fair)->for($this->school)->create();

        expect($this->service->apply($this->fair, $this->school, $this->rep, 'Better case this time.'))
            ->status->toBe(GrantStatus::Pending);
    });

    it('does not confuse two fairs or two schools', function () {
        Grant::factory()->for($this->fair)->for($this->school)->create();
        $otherFair = Fair::factory()->registrationOpen()->create();

        expect($this->service->apply($otherFair, $this->school, $this->rep, 'Please.'))
            ->status->toBe(GrantStatus::Pending);
    });
});

describe('approving', function () {
    beforeEach(function () {
        $this->grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');
    });

    it('approves a free grant and zeroes the price', function () {
        $this->service->approve($this->grant, $this->coordinator, GrantBenefit::Free);

        expect($this->grant->refresh()->status)->toBe(GrantStatus::Approved)
            ->and($this->grant->benefit_type)->toBe(GrantBenefit::Free)
            ->and($this->grant->decided_by)->toBe($this->coordinator->id)
            ->and($this->grant->decided_at)->not->toBeNull()
            ->and($this->fair->priceFor($this->school))->toBe(0);
    });

    it('approves a custom price', function () {
        $this->service->approve($this->grant, $this->coordinator, GrantBenefit::CustomPrice, customPriceCents: 5000);

        expect($this->grant->refresh()->custom_price_cents)->toBe(5000)
            ->and($this->grant->percent_off)->toBeNull()
            ->and($this->fair->priceFor($this->school))->toBe(5000);
    });

    it('approves a percentage off', function () {
        $this->service->approve($this->grant, $this->coordinator, GrantBenefit::PercentOff, percentOff: 40);

        expect($this->grant->refresh()->percent_off)->toBe(40)
            ->and($this->grant->custom_price_cents)->toBeNull()
            ->and($this->fair->priceFor($this->school))->toBe(12900);
    });

    it('refuses to approve a custom price with no price', function () {
        // Otherwise priceFor() falls through to list price and the school is
        // told it has a grant, then charged in full.
        expect(fn () => $this->service->approve($this->grant, $this->coordinator, GrantBenefit::CustomPrice))
            ->toThrow(GrantNotAllowed::class, 'what the grant is worth');
    });

    it('refuses to approve a percentage off with no percentage or an impossible one', function (?int $percent) {
        expect(fn () => $this->service->approve(
            $this->grant, $this->coordinator, GrantBenefit::PercentOff, percentOff: $percent,
        ))->toThrow(GrantNotAllowed::class, 'what the grant is worth');
    })->with([
        'missing' => null,
        'zero' => 0,
        'over a hundred' => 101,
        'negative' => -10,
    ]);

    it('clears the parameters that do not belong to the chosen benefit', function () {
        $this->service->approve(
            $this->grant, $this->coordinator, GrantBenefit::Free,
            customPriceCents: 5000, percentOff: 40,
        );

        expect($this->grant->refresh()->custom_price_cents)->toBeNull()
            ->and($this->grant->percent_off)->toBeNull();
    });

    it('fires the approval event', function () {
        Event::fake([GrantApproved::class]);

        $this->service->approve($this->grant, $this->coordinator, GrantBenefit::Free);

        Event::assertDispatched(GrantApproved::class);
    });

    it('refuses to approve something already decided', function () {
        $this->service->approve($this->grant, $this->coordinator, GrantBenefit::Free);

        expect(fn () => $this->service->approve($this->grant, $this->coordinator, GrantBenefit::Free))
            ->toThrow(GrantNotAllowed::class, 'already been decided');
    });
});

describe('denying', function () {
    beforeEach(function () {
        $this->grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');
    });

    it('records the decision and the reason', function () {
        $this->service->deny($this->grant, $this->coordinator, 'Funds for this fair are committed.');

        expect($this->grant->refresh()->status)->toBe(GrantStatus::Denied)
            ->and($this->grant->denial_reason)->toBe('Funds for this fair are committed.')
            ->and($this->grant->decided_by)->toBe($this->coordinator->id);
    });

    it('leaves the price at list', function () {
        $this->service->deny($this->grant, $this->coordinator, 'No.');

        expect($this->fair->priceFor($this->school))->toBe(21500);
    });

    it('requires a reason, because it goes into the email the school receives', function () {
        expect(fn () => $this->service->deny($this->grant, $this->coordinator, '   '))
            ->toThrow(GrantNotAllowed::class, 'Give a reason');
    });

    it('fires the denial event', function () {
        Event::fake([GrantDenied::class]);

        $this->service->deny($this->grant, $this->coordinator, 'No.');

        Event::assertDispatched(GrantDenied::class);
    });
});

describe('revoking', function () {
    it('revokes an approved, unused grant and restores list price', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();

        $this->service->revoke($grant, $this->coordinator, 'Awarded in error.');

        expect($grant->refresh()->status)->toBe(GrantStatus::Revoked)
            ->and($this->fair->priceFor($this->school))->toBe(21500)
            // What it was worth is still on the record.
            ->and($grant->benefit_type)->toBe(GrantBenefit::Free);
    });

    it('refuses once a live registration has been priced under it', function () {
        // The discount was given in writing. Clawing it back means invoicing a
        // school for something it was granted.
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();
        Registration::factory()->free()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['grant_id' => $grant->id]);

        expect(fn () => $this->service->revoke($grant, $this->coordinator))
            ->toThrow(GrantNotAllowed::class, 'already been used');
    });

    it('allows revoking once the registration using it is cancelled', function () {
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();
        Registration::factory()->cancelled()->forEvent($this->fair)->forOrganization($this->school)
            ->create(['grant_id' => $grant->id]);

        expect($this->service->revoke($grant, $this->coordinator))->status->toBe(GrantStatus::Revoked);
    });

    it('refuses to revoke anything that is not approved', function (string $state) {
        $grant = Grant::factory()->{$state}()->for($this->fair)->for($this->school)->create();

        expect(fn () => $this->service->revoke($grant, $this->coordinator))
            ->toThrow(GrantNotAllowed::class, 'Only an approved grant');
    })->with(['pending', 'denied', 'withdrawn']);

    it('fires the revocation event', function () {
        Event::fake([GrantRevoked::class]);
        $grant = Grant::factory()->free()->for($this->fair)->for($this->school)->create();

        $this->service->revoke($grant, $this->coordinator);

        Event::assertDispatched(GrantRevoked::class);
    });
});

describe('withdrawing', function () {
    it('lets the applying school take a pending application back', function () {
        $grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');

        $this->service->withdraw($grant, $this->rep);

        expect($grant->refresh()->status)->toBe(GrantStatus::Withdrawn);
    });

    it('frees the slot so the school can apply again', function () {
        $grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');
        $this->service->withdraw($grant, $this->rep);

        expect($this->service->apply($this->fair, $this->school, $this->rep, 'Second attempt.'))
            ->status->toBe(GrantStatus::Pending);
    });

    it('refuses to withdraw a decided application', function () {
        // A denial is the coordinator's decision and it stands.
        $grant = Grant::factory()->denied()->for($this->fair)->for($this->school)->create();

        expect(fn () => $this->service->withdraw($grant, $this->rep))
            ->toThrow(GrantNotAllowed::class, 'already been decided');
    });

    it('refuses a rep from another school', function () {
        $grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');
        $stranger = User::factory()->rep()->create();

        expect(fn () => $this->service->withdraw($grant, $stranger))
            ->toThrow(GrantNotAllowed::class);
    });

    it('fires the withdrawal event', function () {
        Event::fake([GrantWithdrawn::class]);
        $grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');

        $this->service->withdraw($grant, $this->rep);

        Event::assertDispatched(GrantWithdrawn::class);
    });
});

describe('lookups', function () {
    it('reports whether a live application exists', function () {
        expect($this->service->hasLiveApplication($this->fair, $this->school))->toBeFalse();

        $grant = $this->service->apply($this->fair, $this->school, $this->rep, 'Please.');

        expect($this->service->hasLiveApplication($this->fair, $this->school))->toBeTrue()
            ->and($this->service->currentApplication($this->fair, $this->school)?->id)->toBe($grant->id);

        $this->service->withdraw($grant, $this->rep);

        expect($this->service->hasLiveApplication($this->fair, $this->school))->toBeFalse()
            ->and($this->service->currentApplication($this->fair, $this->school))->toBeNull();
    });
});
