<?php

use App\Enums\GrantStatus;
use App\Enums\MembershipStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventInterest;
use App\Models\FaqItem;
use App\Models\Grant;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FairFixtureSeeder;
use Database\Seeders\ProductionSeeder;
use UClemmer\LaravelCore\Content\Content;
use UClemmer\LaravelCore\Content\ContentType;

describe('the production seed', function () {
    beforeEach(fn () => $this->seed(ProductionSeeder::class));

    it('provisions a coordinator who can reach the admin panel', function () {
        $coordinator = User::query()->where('email', config('fair.coordinator.email'))->first();

        expect($coordinator)->not->toBeNull()
            ->and($coordinator->hasRole('coordinator'))->toBeTrue()
            ->and($coordinator->can('admin.access'))->toBeTrue()
            // A coordinator is not a member of anything — they administer
            // through the panel, never through the rep portal.
            ->and($coordinator->organization_id)->toBeNull()
            ->and($coordinator->membership_status)->toBeNull();
    });

    it('seeds the page copy as core content blocks, published', function () {
        $blocks = Content::query()->ofType(ContentType::Block)->published()->pluck('slug');

        expect($blocks)->toContain('home.hero', 'about.body', 'sponsors.intro', 'contact.intro')
            // Every app table this app might have used for copy is core's
            // instead (doc 03) — assert we did not quietly build a parallel one.
            ->and(Content::query()->ofType(ContentType::Block)->count())->toBeGreaterThanOrEqual(9);
    });

    it('flags the refund policy as owner copy that must be replaced', function () {
        // doc 01 lists this as an open question; the placeholder must announce
        // itself rather than read as settled policy.
        $policy = Content::query()->where('slug', 'policy.refunds')->firstOrFail();

        expect($policy->title)->toContain('TODO-OWNER')
            ->and($policy->body)->toContain('placeholder');
    });

    it('seeds the four sponsor schools in billing order', function () {
        expect(Sponsor::query()->ordered()->pluck('name')->all())->toBe([
            'Baylor School',
            'Girls Preparatory School',
            'McCallie School',
            'St. Andrews-Sewanee School',
        ]);
    });

    it('seeds a published FAQ', function () {
        expect(FaqItem::query()->published()->count())->toBeGreaterThanOrEqual(10);
    });

    it('seeds five past fairs and one upcoming one', function () {
        expect(Event::query()->orderBy('starts_at')->pluck('slug')->all())->toBe([
            'college-fair-2022',
            'college-fair-2023',
            'college-fair-2024',
            'college-fair-2025',
            'college-fair-2026',
            'college-fair-2027',
        ])
            // Five past fairs, not one: LastEvent and AnyPreviousEvent are
            // indistinguishable with a single year of history (doc 07 §2), and
            // the historical roster import has nowhere to put a 2023 roster if
            // the 2023 fair is not a row.
            ->and(Event::query()->previousPublished()->count())->toBe(5);
    });

    it('leaves every past fair published, so its roster can be shown', function () {
        // A past fair that is unpublished is invisible to previousPublished(),
        // which means invisible to the Last Year roster and to every cross-year
        // campaign audience -- so importing a roster into it would be silent.
        $past = Event::query()->where('starts_at', '<', now())->get();

        expect($past)->toHaveCount(5)
            ->and($past->every(fn (Event $fair): bool => $fair->is_published))->toBeTrue();
    });

    it('leaves the 2027 fair unpublished because its date and price are placeholders', function () {
        // An unpublished event cannot take money, so a forgotten placeholder
        // cannot quietly charge an organization the wrong fee.
        $fair = Event::query()->where('slug', 'college-fair-2027')->firstOrFail();

        expect($fair->is_published)->toBeFalse()
            ->and($fair->isRegistrationOpen())->toBeFalse()
            ->and($fair->name)->toContain('TODO-OWNER');
    });

    it('invents no organizations, reps, registrations, grants or payments', function () {
        expect(Organization::query()->count())->toBe(0)
            ->and(Registration::query()->count())->toBe(0)
            ->and(Grant::query()->count())->toBe(0)
            ->and(User::query()->count())->toBe(1);
    });

    it('is safe to run twice', function () {
        $this->seed(ProductionSeeder::class);

        expect(User::query()->count())->toBe(1)
            ->and(Sponsor::query()->count())->toBe(4)
            ->and(Event::query()->count())->toBe(6)
            ->and(Content::query()->ofType(ContentType::Block)->count())->toBe(9);
    });

    it('is safe to run after the coordinator deletes a block', function () {
        // Content soft-deletes, and its unique index is (type, slug) with no
        // deleted_at column -- so a deleted block still owns its slug. A guard
        // that asked the default scope would call the slug free, and the insert
        // would abort the whole deploy's seed on a UNIQUE violation.
        Content::query()->where('slug', 'home.hero')->firstOrFail()->delete();

        $this->seed(ProductionSeeder::class);

        expect(Content::query()->where('slug', 'home.hero')->exists())->toBeFalse()
            ->and(Content::query()->withTrashed()->where('slug', 'home.hero')->count())->toBe(1);
    });
});

describe('the development seed', function () {
    beforeEach(fn () => $this->seed(DatabaseSeeder::class));

    it('opens the current fair so the portal has something live to work on', function () {
        $fair = Event::query()->where('slug', 'college-fair-2027')->firstOrFail();

        expect($fair->is_published)->toBeTrue()
            ->and($fair->isRegistrationOpen())->toBeTrue();
    });

    it('gives the cross-year audiences organizations that lapsed after each past fair', function () {
        $fair2026 = Event::query()->where('slug', 'college-fair-2026')->firstOrFail();
        $fair2027 = Event::query()->where('slug', 'college-fair-2027')->firstOrFail();

        $registeredIn2026 = Registration::query()->where('event_id', $fair2026->id)
            ->pluck('organization_id');
        $registeredIn2027 = Registration::query()->where('event_id', $fair2027->id)
            ->pluck('organization_id');

        expect($registeredIn2026->diff($registeredIn2027))->not->toBeEmpty();
    });

    it('gives every membership state an organization to live in', function () {
        expect(User::query()->where('membership_status', MembershipStatus::Active)->exists())->toBeTrue()
            ->and(User::query()->where('membership_status', MembershipStatus::Pending)->exists())->toBeTrue()
            ->and(User::query()->where('membership_status', MembershipStatus::Retired)->exists())->toBeTrue();
    });

    it('gives the campaign fallback both of its cases', function () {
        // An organization with no active rep but a generic address gets one recipient;
        // one with neither is dropped with a log (doc 07 §2 rule 1).
        $noActiveReps = Organization::query()
            ->whereDoesntHave('users', fn ($q) => $q->where('membership_status', MembershipStatus::Active))
            ->whereHas('registrations')
            ->get();

        expect($noActiveReps->where('admissions_email', '!=', null))->not->toBeEmpty()
            ->and($noActiveReps->whereNull('admissions_email'))->not->toBeEmpty();
    });

    it('seeds a pair of organizations that normalize to the same name', function () {
        $duplicated = Organization::query()
            ->selectRaw('normalized_name, count(*) as total')
            ->groupBy('normalized_name')
            ->havingRaw('count(*) > 1')
            ->get();

        expect($duplicated)->not->toBeEmpty();
    });

    it('seeds a grant in every status', function () {
        $statuses = Grant::query()->pluck('status')->unique()->values();

        expect($statuses)->toHaveCount(count(GrantStatus::cases()));
    });

    it('applies the approved grants to real registrations, so the snapshot differs from list price', function () {
        $fair = Event::query()->where('slug', 'college-fair-2027')->firstOrFail();

        $discounted = Registration::query()
            ->whereNotNull('grant_id')
            ->where('event_id', $fair->id)
            ->get();

        expect($discounted)->not->toBeEmpty()
            ->and($discounted->pluck('price_cents')->unique()->all())->not->toBe([$fair->price_cents]);
    });

    it('seeds a free registration with no payment method and no payment row', function () {
        $free = Registration::query()->where('price_cents', 0)->firstOrFail();

        expect($free->payment_method)->toBeNull()
            ->and($free->status)->toBe(RegistrationStatus::Confirmed)
            ->and($free->payments()->count())->toBe(0);
    });

    it('seeds the registration states the admin panel has to cope with', function () {
        expect(Registration::query()->where('status', RegistrationStatus::PendingPayment)->exists())->toBeTrue()
            ->and(Registration::query()->where('status', RegistrationStatus::Cancelled)->exists())->toBeTrue()
            ->and(Registration::query()->where('show_on_roster', false)->exists())->toBeTrue()
            ->and(Registration::query()->whereNull('user_id')->exists())->toBeTrue();
    });

    it('seeds an interest list with a notified row and un-notified ones', function () {
        expect(EventInterest::query()->unnotified()->count())->toBeGreaterThan(0)
            ->and(EventInterest::query()->whereNotNull('notified_at')->count())->toBeGreaterThan(0);
    });

    it('invents nothing about an organization that is a real institution', function () {
        // The fixtures name real colleges so the development roster is worth
        // looking at, and the factory invents a website, an inbox, a phone and
        // an address to go with the name. On an invented name that is
        // placeholder data; on a real one it is wrong data — and because both
        // real-data seeders only fill columns that are EMPTY, it also blocks
        // the researched value from ever landing. Twenty-six organizations
        // carried faker output, `https://sawayn.com` on Rhodes College among
        // them, while the real admissions page sat in the seed data unused.
        //
        // Asserted over the whole seed rather than per seeder on purpose: every
        // seeder involved was correct on its own, and only running them
        // together shows it.
        $researched = json_decode(
            (string) file_get_contents(base_path('database/seeders/data/admissions-offices.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $disagreements = collect($researched)
            ->mapWithKeys(fn (array $office, string $name): array => [
                Organization::normalizeName($name) => $office,
            ])
            ->map(function (array $office, string $key): array {
                $organization = Organization::query()->where('normalized_name', $key)->first();

                if (! $organization instanceof Organization) {
                    return [];
                }

                return collect(['website', 'admissions_email', 'admissions_phone', 'city'])
                    ->filter(fn (string $column): bool => filled($office[$column] ?? null)
                        && filled($organization->{$column})
                        && $organization->{$column} !== $office[$column])
                    ->mapWithKeys(fn (string $column): array => [
                        $column => $organization->{$column}.' should be '.$office[$column],
                    ])
                    ->all();
            })
            ->filter()
            ->all();

        expect($disagreements)->toBe([]);
    });

    it('does not duplicate its fixtures when run again', function () {
        $before = Organization::query()->count();

        $this->seed(FairFixtureSeeder::class);

        expect(Organization::query()->count())->toBe($before);
    });
});
