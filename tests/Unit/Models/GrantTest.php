<?php

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('casts', function () {
    it('casts status, benefit, money and the decision timestamp', function () {
        $grant = Grant::factory()->customPrice(5000)->create();

        expect($grant->status)->toBe(GrantStatus::Approved)
            ->and($grant->benefit_type)->toBe(GrantBenefit::CustomPrice)
            ->and($grant->custom_price_cents)->toBeInt()->toBe(5000)
            ->and($grant->decided_at)->toBeInstanceOf(Carbon::class);
    });

    it('leaves a pending application undecided and unpriced', function () {
        $grant = Grant::factory()->create();

        expect($grant->status)->toBe(GrantStatus::Pending)
            ->and($grant->benefit_type)->toBeNull()
            ->and($grant->decided_at)->toBeNull()
            ->and($grant->decided_by)->toBeNull();
    });
});

describe('relationships', function () {
    it('resolves the school, event, requester, decider and registrations', function () {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create();
        $requester = User::factory()->rep($organization)->create();

        $grant = Grant::factory()->free()->for($organization)->for($event)
            ->create(['requested_by' => $requester->id]);
        Registration::factory()->free()->create(['grant_id' => $grant->id]);

        expect($grant->organization->is($organization))->toBeTrue()
            ->and($grant->event->is($event))->toBeTrue()
            ->and($grant->requester->is($requester))->toBeTrue()
            ->and($grant->decider)->not->toBeNull()
            ->and($grant->registrations()->count())->toBe(1);
    });
});

describe('usage', function () {
    it('is unused before any registration references it', function () {
        expect(Grant::factory()->free()->create()->isUsed())->toBeFalse();
    });

    it('is used once a live registration is priced under it', function () {
        // A used grant can no longer be revoked: the money has settled or is in
        // the post, and clawing the discount back means invoicing a school for
        // a discount granted in writing.
        $grant = Grant::factory()->free()->create();
        Registration::factory()->free()->create(['grant_id' => $grant->id]);

        expect($grant->isUsed())->toBeTrue();
    });

    it('is unused again once the registration referencing it is cancelled', function () {
        $grant = Grant::factory()->free()->create();
        Registration::factory()->cancelled()->create(['grant_id' => $grant->id]);

        expect($grant->isUsed())->toBeFalse();
    });
});

describe('benefit summary', function () {
    it('describes what was granted', function () {
        expect(Grant::factory()->free()->create()->benefitSummary())->toBe('Free registration')
            ->and(Grant::factory()->customPrice(5000)->create()->benefitSummary())->toBe('Reduced rate of $50.00')
            ->and(Grant::factory()->percentOff(25)->create()->benefitSummary())->toBe('25% off registration');
    });

    it('has nothing to describe before a decision', function () {
        expect(Grant::factory()->create()->benefitSummary())->toBeNull();
    });
});

describe('scopes', function () {
    it('scopes to approved grants only', function () {
        Grant::factory()->free()->create();
        Grant::factory()->create();
        Grant::factory()->denied()->create();
        Grant::factory()->revoked()->create();

        expect(Grant::query()->approved()->count())->toBe(1);
    });

    it('counts every status except withdrawn as blocking a second application', function () {
        // Only a change of mind frees the slot; a denial is final for that fair.
        Grant::factory()->create();
        Grant::factory()->free()->create();
        Grant::factory()->denied()->create();
        Grant::factory()->revoked()->create();
        Grant::factory()->withdrawn()->create();

        expect(Grant::query()->blockingReapplication()->count())->toBe(4);
    });
});
