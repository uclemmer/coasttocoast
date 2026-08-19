<?php

use App\Filament\Admin\Resources\FaqItemResource\Pages\CreateFaqItem;
use App\Filament\Admin\Resources\FaqItemResource\Pages\ListFaqItems;
use App\Filament\Admin\Resources\SponsorResource\Pages\CreateSponsor;
use App\Filament\Admin\Resources\SponsorResource\Pages\EditSponsor;
use App\Filament\Admin\Resources\SponsorResource\Pages\ListSponsors;
use App\Filament\Admin\Resources\SponsorResource\RelationManagers\StaffRelationManager;
use App\Models\FaqItem;
use App\Models\Sponsor;
use App\Models\User;
use Filament\Facades\Filament;
use UClemmer\LaravelCore\Admin\Resources\ContactSubmissionResource;
use UClemmer\LaravelCore\Admin\Resources\ContentResource;

beforeEach(function () {
    usingAdminPanel();
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('sponsors', function () {
    it('creates a sponsor', function () {
        livewire(CreateSponsor::class)
            ->fillForm(['name' => 'Baylor School', 'website' => 'https://www.baylorschool.org'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Sponsor::query()->where('name', 'Baylor School')->exists())->toBeTrue();
    });

    it('lists sponsors in the coordinator\'s chosen order, not alphabetically', function () {
        // Sponsors pay for billing position.
        $second = Sponsor::factory()->ordered(1)->create(['name' => 'Alpha']);
        $first = Sponsor::factory()->ordered(0)->create(['name' => 'Zulu']);

        livewire(ListSponsors::class)
            ->assertCanSeeTableRecords([$first, $second], inOrder: true);
    });

    it('reorders by dragging', function () {
        $a = Sponsor::factory()->ordered(0)->create();
        $b = Sponsor::factory()->ordered(1)->create();

        livewire(ListSponsors::class)->call('reorderTable', [$b->id, $a->id]);

        expect($b->refresh()->sort_order)->toBeLessThan($a->refresh()->sort_order);
    });

    it('manages the staff listed under a sponsor', function () {
        $sponsor = Sponsor::factory()->create();

        livewire(StaffRelationManager::class, [
            'ownerRecord' => $sponsor,
            'pageClass' => EditSponsor::class,
        ])->callTableAction('create', data: ['name' => 'Meg Conner', 'title' => 'Fair Coordinator']);

        expect($sponsor->staff()->count())->toBe(1)
            ->and($sponsor->staff()->first()->name)->toBe('Meg Conner');
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListSponsors::class)->assertForbidden();
    });
});

describe('FAQ', function () {
    it('creates a question, published by default', function () {
        livewire(CreateFaqItem::class)
            ->fillForm(['question' => 'Where do we park?', 'answer' => 'Behind the centre.'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(FaqItem::query()->where('question', 'Where do we park?')->first())
            ->is_published->toBeTrue();
    });

    it('flags the seeded placeholders so they are found before launch', function () {
        // Better a coordinator finds them than a representative looking for
        // parking on the night.
        $needsCopy = FaqItem::factory()->create([
            'answer' => 'TODO-OWNER: transcribe the parking directions.',
        ]);
        $done = FaqItem::factory()->create(['answer' => 'Behind the centre.']);

        livewire(ListFaqItems::class)
            ->assertTableColumnStateSet('needs_owner', 'TODO-OWNER', $needsCopy)
            ->assertTableColumnStateSet('needs_owner', null, $done);
    });

    it('reorders by dragging', function () {
        $a = FaqItem::factory()->ordered(0)->create();
        $b = FaqItem::factory()->ordered(1)->create();

        livewire(ListFaqItems::class)->call('reorderTable', [$b->id, $a->id]);

        expect($b->refresh()->sort_order)->toBeLessThan($a->refresh()->sort_order);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(ListFaqItems::class)->assertForbidden();
    });
});

describe('the laravel-core resources this app configures rather than builds', function () {
    it('registers content and contact on the admin panel', function () {
        // Doc 03: page copy is core Content of type `block`, and the contact
        // inbox is core's. Card 2.4 says configure and verify, do not rebuild.
        $resources = Filament::getPanel('core')->getResources();

        expect($resources)->toContain(ContentResource::class)
            ->toContain(ContactSubmissionResource::class);
    });

    it('lets the coordinator reach both', function () {
        expect(ContentResource::canAccess())->toBeTrue()
            ->and(ContactSubmissionResource::canAccess())->toBeTrue();
    });

    it('does not build a parallel content or contact table of its own', function () {
        expect(class_exists('App\\Models\\ContentBlock'))->toBeFalse()
            ->and(class_exists('App\\Models\\ContactSubmission'))->toBeFalse();
    });
});
