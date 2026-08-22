<?php

use App\Livewire\Staff\Faq\Edit as EditFaqItem;
use App\Livewire\Staff\Faq\Index as FaqIndex;
use App\Models\FaqItem;
use App\Models\User;

/*
 * The staff FAQ screens (docs/13).
 *
 * The four tests ported from ContentResourcesTest's `FAQ` block keep their
 * intent; two keep their names. The Filament originals stay where they are
 * until app/Filament is deleted, because that resource is still live.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('writing a question', function () {
    it('creates a question, published by default', function () {
        livewire(EditFaqItem::class)
            ->set('question', 'Where do we park?')
            ->set('answer', 'Behind the centre.')
            ->call('save')
            ->assertHasNoErrors();

        expect(FaqItem::query()->where('question', 'Where do we park?')->first())
            ->is_published->toBeTrue();
    });

    it('requires both a question and an answer', function () {
        livewire(EditFaqItem::class)->call('save')->assertHasErrors(['question', 'answer']);
    });

    it('appends a new question rather than jumping the order', function () {
        FaqItem::factory()->ordered(1)->create();

        livewire(EditFaqItem::class)
            ->set('question', 'Newest')
            ->set('answer', 'x')
            ->call('save');

        expect(FaqItem::query()->orderBy('sort_order')->pluck('question')->last())->toBe('Newest');
    });

    it('edits an existing question', function () {
        $item = FaqItem::factory()->create(['question' => 'Old']);

        livewire(EditFaqItem::class, ['item' => $item])->set('question', 'New')->call('save');

        expect($item->refresh()->question)->toBe('New');
    });

    /*
     * The editor is a textarea, so what is typed and what a visitor reads are
     * not the same string. The preview renders through the same
     * `Str::markdown()` the public page uses; if one is ever swapped for
     * another renderer this is what notices.
     */
    it('previews the answer as markdown', function () {
        $preview = livewire(EditFaqItem::class)
            ->set('answer', 'Parking is **behind** the centre.')
            ->instance()
            ->preview();

        expect($preview)->toContain('<strong>behind</strong>');
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(EditFaqItem::class)->assertForbidden();
    });
});

describe('the question list', function () {
    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(FaqIndex::class)->assertForbidden();
    });

    it('flags the seeded placeholders so they are found before launch', function () {
        // Better a coordinator finds them than a representative looking for
        // parking on the night.
        $needsCopy = FaqItem::factory()->create([
            'answer' => 'TODO-OWNER: transcribe the parking directions.',
        ]);
        $done = FaqItem::factory()->create(['answer' => 'Behind the centre.']);

        $page = livewire(FaqIndex::class);

        expect($page->instance()->needsCopy($needsCopy))->toBeTrue()
            ->and($page->instance()->needsCopy($done))->toBeFalse()
            ->and($page->instance()->needsCopyCount())->toBe(1);
    });

    it('filters by published state', function () {
        $live = FaqItem::factory()->create(['is_published' => true]);
        $hidden = FaqItem::factory()->create(['is_published' => false]);

        $page = livewire(FaqIndex::class);

        expect($page->set('published', 'yes')->instance()->items()->pluck('id')->all())->toBe([$live->id])
            ->and($page->set('published', 'no')->instance()->items()->pluck('id')->all())->toBe([$hidden->id])
            ->and($page->set('published', '')->instance()->items()->count())->toBe(2);
    });

    it('searches the question text', function () {
        FaqItem::factory()->create(['question' => 'Where do we park?']);
        FaqItem::factory()->create(['question' => 'What does it cost?']);

        $found = livewire(FaqIndex::class)->set('search', 'park')->instance()->items();

        expect($found->pluck('question')->all())->toBe(['Where do we park?']);
    });
});

describe('reordering', function () {
    /*
     * Replaces "reorders by dragging" — see docs/13 for why the rebuild uses
     * buttons. The behaviour under test is unchanged.
     */
    it('moves a question up past its neighbour', function () {
        $a = FaqItem::factory()->ordered(0)->create();
        $b = FaqItem::factory()->ordered(1)->create();

        livewire(FaqIndex::class)->call('moveUp', $b->id);

        expect($b->refresh()->sort_order)->toBeLessThan($a->refresh()->sort_order);
    });

    it('is refused while a filter is active', function () {
        // Not only the search box: filtering by published state hides rows too,
        // and "move up" past a hidden neighbour is just as unpredictable.
        $a = FaqItem::factory()->ordered(0)->create(['is_published' => false]);
        $b = FaqItem::factory()->ordered(1)->create(['is_published' => true]);

        livewire(FaqIndex::class)->set('published', 'yes')->call('moveUp', $b->id);

        expect($a->refresh()->sort_order)->toBeLessThan($b->refresh()->sort_order);
    });
});

describe('publishing from the list', function () {
    /*
     * Filament's toggle lived only in the form, so hiding a question meant
     * opening it. This is the one thing done to a FAQ row in a hurry.
     */
    it('hides and republishes without opening the editor', function () {
        $item = FaqItem::factory()->create(['is_published' => true]);

        $page = livewire(FaqIndex::class)->call('togglePublished', $item->id);
        expect($item->refresh()->is_published)->toBeFalse();

        $page->call('togglePublished', $item->id);
        expect($item->refresh()->is_published)->toBeTrue();
    });

    it('refuses a user without the permission', function () {
        $item = FaqItem::factory()->create(['is_published' => true]);
        $this->actingAs(User::factory()->rep()->create());

        // mount() refuses first, which is the point: the action is unreachable
        // rather than merely unauthorised.
        livewire(FaqIndex::class)->assertForbidden();

        expect($item->refresh()->is_published)->toBeTrue();
    });
});

describe('removing questions', function () {
    it('removes one', function () {
        $item = FaqItem::factory()->create();

        livewire(FaqIndex::class)->call('confirmDelete', $item->id)->call('delete');

        expect(FaqItem::query()->whereKey($item->id)->exists())->toBeFalse();
    });

    it('removes the ticked ones in bulk', function () {
        $a = FaqItem::factory()->create();
        $b = FaqItem::factory()->create();
        $keep = FaqItem::factory()->create();

        livewire(FaqIndex::class)
            ->set('selected', [(string) $a->id, (string) $b->id])
            ->call('deleteSelected');

        expect(FaqItem::query()->pluck('id')->all())->toBe([$keep->id]);
    });

    it('drops the selection when a filter changes', function () {
        $item = FaqItem::factory()->create(['is_published' => true]);

        $page = livewire(FaqIndex::class)
            ->set('selected', [(string) $item->id])
            ->set('published', 'no');

        expect($page->get('selected'))->toBe([]);
    });
});
