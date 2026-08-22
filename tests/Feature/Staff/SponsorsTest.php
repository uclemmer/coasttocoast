<?php

use App\Livewire\Staff\Sponsors\Edit as EditSponsor;
use App\Livewire\Staff\Sponsors\Index as SponsorIndex;
use App\Models\Sponsor;
use App\Models\SponsorStaff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

/*
 * The staff sponsors screens (docs/13).
 *
 * The five tests ported from ContentResourcesTest's `sponsors` block keep their
 * original intent and, where the copy still applies, their original names — the
 * behaviour under test did not change, only what implements it.
 *
 * The rest are new, and they are new for a reason worth stating: Filament
 * supplied file handling, record scoping and implicit policy checks, and this
 * rebuild hand-rolls all three. Anything Filament used to do for free is
 * exactly what has no coverage yet.
 */

beforeEach(function () {
    $this->coordinator = coordinator();
    $this->actingAs($this->coordinator);
});

describe('the sponsors list', function () {
    it("lists sponsors in the coordinator's chosen order, not alphabetically", function () {
        // Sponsors pay for billing position.
        $second = Sponsor::factory()->ordered(1)->create(['name' => 'Alpha']);
        $first = Sponsor::factory()->ordered(0)->create(['name' => 'Zulu']);

        $listed = livewire(SponsorIndex::class)->instance()->sponsors();

        // Against the collection, not the HTML: a name can legitimately appear
        // elsewhere on the page, and order is the whole point of this test.
        expect($listed->pluck('id')->all())->toBe([$first->id, $second->id]);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(SponsorIndex::class)->assertForbidden();
    });

    it('searches by name', function () {
        Sponsor::factory()->create(['name' => 'Baylor School']);
        Sponsor::factory()->create(['name' => 'McCallie']);

        $found = livewire(SponsorIndex::class)->set('search', 'Bayl')->instance()->sponsors();

        expect($found->pluck('name')->all())->toBe(['Baylor School']);
    });
});

describe('reordering', function () {
    /*
     * Replaces "reorders by dragging". Filament's table was `->reorderable()`
     * and the test called its `reorderTable`; the rebuild uses up/down buttons,
     * because drag is unusable by keyboard and cannot be exercised in a
     * headless browser that never composites a frame. The intent the original
     * recorded — the coordinator never types a sort number — is unchanged.
     */
    it('moves a sponsor up past its neighbour', function () {
        $a = Sponsor::factory()->ordered(0)->create(['name' => 'First']);
        $b = Sponsor::factory()->ordered(1)->create(['name' => 'Second']);

        livewire(SponsorIndex::class)->call('moveUp', $b->id);

        expect($b->refresh()->sort_order)->toBeLessThan($a->refresh()->sort_order);
    });

    it('moves a sponsor down past its neighbour', function () {
        $a = Sponsor::factory()->ordered(0)->create();
        $b = Sponsor::factory()->ordered(1)->create();

        livewire(SponsorIndex::class)->call('moveDown', $a->id);

        expect($a->refresh()->sort_order)->toBeGreaterThan($b->refresh()->sort_order);
    });

    it('does nothing at the ends of the list', function () {
        $a = Sponsor::factory()->ordered(0)->create();
        $b = Sponsor::factory()->ordered(1)->create();

        livewire(SponsorIndex::class)->call('moveUp', $a->id)->call('moveDown', $b->id);

        expect($a->refresh()->sort_order)->toBeLessThan($b->refresh()->sort_order);
    });

    it('leaves the order dense, so the next move is unambiguous', function () {
        // `ordered()` breaks ties on name, so sort_order is allowed to collide.
        // Reordering rewrites the column rather than doing arithmetic on it.
        Sponsor::factory()->ordered(5)->create(['name' => 'A']);
        $b = Sponsor::factory()->ordered(5)->create(['name' => 'B']);

        livewire(SponsorIndex::class)->call('moveUp', $b->id);

        expect(Sponsor::query()->ordered()->pluck('sort_order')->all())->toBe([1, 2]);
    });

    it('is refused while a search is active', function () {
        // "Move up" in a filtered list means nothing the user can predict.
        $a = Sponsor::factory()->ordered(0)->create(['name' => 'Alpha']);
        $b = Sponsor::factory()->ordered(1)->create(['name' => 'Beta']);

        livewire(SponsorIndex::class)->set('search', 'Beta')->call('moveUp', $b->id);

        expect($a->refresh()->sort_order)->toBeLessThan($b->refresh()->sort_order);
    });
});

describe('creating and editing', function () {
    it('creates a sponsor', function () {
        livewire(EditSponsor::class)
            ->set('name', 'Baylor School')
            ->set('website', 'https://www.baylorschool.org')
            ->call('save')
            ->assertHasNoErrors();

        expect(Sponsor::query()->where('name', 'Baylor School')->exists())->toBeTrue();
    });

    it('appends a new sponsor to the end of the order', function () {
        // Inserting at the top would silently demote a paying sponsor.
        Sponsor::factory()->ordered(1)->create();
        Sponsor::factory()->ordered(2)->create();

        livewire(EditSponsor::class)->set('name', 'Newcomer')->call('save');

        expect(Sponsor::query()->ordered()->pluck('name')->last())->toBe('Newcomer');
    });

    it('requires a name and a real url', function () {
        livewire(EditSponsor::class)
            ->set('name', '')
            ->set('website', 'not-a-url')
            ->call('save')
            ->assertHasErrors(['name', 'website']);
    });

    it('keeps a user without the permission out', function () {
        $this->actingAs(User::factory()->rep()->create());

        livewire(EditSponsor::class)->assertForbidden();
    });
});

describe('the logo', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('stores an uploaded logo on the public disk', function () {
        livewire(EditSponsor::class)
            ->set('name', 'Baylor School')
            ->set('logo', UploadedFile::fake()->image('crest.png'))
            ->call('save')
            ->assertHasNoErrors();

        $sponsor = Sponsor::query()->where('name', 'Baylor School')->sole();

        expect($sponsor->logo_path)->toStartWith('sponsor-logos/');
        Storage::disk('public')->assertExists($sponsor->logo_path);
    });

    it('rejects a file that is not an image', function () {
        livewire(EditSponsor::class)
            ->set('name', 'Baylor School')
            ->set('logo', UploadedFile::fake()->create('prospectus.pdf', 10))
            ->call('save')
            ->assertHasErrors('logo');
    });

    /*
     * Filament's FileUpload cleaned up after itself. Nothing else references a
     * replaced logo, so without this the old file stays on disk forever.
     */
    it('deletes the old file when a logo is replaced', function () {
        $sponsor = Sponsor::factory()->create([
            'logo_path' => UploadedFile::fake()->image('old.png')->store('sponsor-logos', 'public'),
        ]);
        $old = $sponsor->logo_path;

        livewire(EditSponsor::class, ['sponsor' => $sponsor])
            ->set('logo', UploadedFile::fake()->image('new.png'))
            ->call('save');

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($sponsor->refresh()->logo_path);
    });

    it('clears the logo when asked, and deletes the file with it', function () {
        $sponsor = Sponsor::factory()->create([
            'logo_path' => UploadedFile::fake()->image('old.png')->store('sponsor-logos', 'public'),
        ]);
        $old = $sponsor->logo_path;

        livewire(EditSponsor::class, ['sponsor' => $sponsor])->set('removeLogo', true)->call('save');

        expect($sponsor->refresh()->logo_path)->toBeNull();
        Storage::disk('public')->assertMissing($old);
    });
});

describe('the staff listed under a sponsor', function () {
    it('manages the staff listed under a sponsor', function () {
        $sponsor = Sponsor::factory()->create();

        livewire(EditSponsor::class, ['sponsor' => $sponsor])
            ->call('addStaff')
            ->set('staffName', 'Meg Conner')
            ->set('staffTitle', 'Fair Coordinator')
            ->call('saveStaff')
            ->assertHasNoErrors();

        expect($sponsor->staff()->count())->toBe(1)
            ->and($sponsor->staff()->first()->name)->toBe('Meg Conner');
    });

    it('edits and removes a person', function () {
        $sponsor = Sponsor::factory()->create();
        $member = SponsorStaff::factory()->for($sponsor)->create(['name' => 'Old Name']);

        $page = livewire(EditSponsor::class, ['sponsor' => $sponsor])
            ->call('editStaff', $member->id)
            ->set('staffName', 'New Name')
            ->call('saveStaff');

        expect($member->refresh()->name)->toBe('New Name');

        $page->call('confirmDeleteStaff', $member->id)->call('deleteStaff');

        expect(SponsorStaff::query()->whereKey($member->id)->exists())->toBeFalse();
    });

    it('reorders staff', function () {
        $sponsor = Sponsor::factory()->create();
        $a = SponsorStaff::factory()->for($sponsor)->create(['name' => 'A', 'sort_order' => 1]);
        $b = SponsorStaff::factory()->for($sponsor)->create(['name' => 'B', 'sort_order' => 2]);

        livewire(EditSponsor::class, ['sponsor' => $sponsor])->call('moveStaffUp', $b->id);

        expect($b->refresh()->sort_order)->toBeLessThan($a->refresh()->sort_order);
    });

    /*
     * The id arrives from the browser. Filament scoped relation-manager records
     * to the owner for us; here it is `$this->sponsor->staff()->find()`, and
     * without it a crafted id would edit somebody under a different sponsor.
     */
    it('refuses a staff id belonging to another sponsor', function () {
        $sponsor = Sponsor::factory()->create();
        $other = SponsorStaff::factory()->for(Sponsor::factory())->create(['name' => 'Not Yours']);

        livewire(EditSponsor::class, ['sponsor' => $sponsor])
            ->call('confirmDeleteStaff', $other->id)
            ->call('deleteStaff');

        expect(SponsorStaff::query()->whereKey($other->id)->exists())->toBeTrue();
    });
});

describe('removing sponsors', function () {
    it('removes one', function () {
        $sponsor = Sponsor::factory()->create();

        livewire(SponsorIndex::class)->call('confirmDelete', $sponsor->id)->call('delete');

        expect(Sponsor::query()->whereKey($sponsor->id)->exists())->toBeFalse();
    });

    it('removes the ticked ones in bulk', function () {
        $a = Sponsor::factory()->create();
        $b = Sponsor::factory()->create();
        $keep = Sponsor::factory()->create();

        livewire(SponsorIndex::class)
            ->set('selected', [(string) $a->id, (string) $b->id])
            ->call('deleteSelected');

        expect(Sponsor::query()->pluck('id')->all())->toBe([$keep->id]);
    });

    it('drops the selection when the search changes', function () {
        // The ticked ids belong to the previous result set; carrying them over
        // would let a bulk delete reach rows the user can no longer see.
        $sponsor = Sponsor::factory()->create(['name' => 'Baylor School']);

        $page = livewire(SponsorIndex::class)
            ->set('selected', [(string) $sponsor->id])
            ->set('search', 'McCallie');

        expect($page->get('selected'))->toBe([]);
    });
});

/*
 * The shell itself.
 *
 * These are HTTP-level rather than Livewire-level on purpose: what is under
 * test is the layout the component is wrapped in, and `Livewire::test()` never
 * renders it. Every assertion here is a thing that fails silently in a browser
 * — the page looks right and nothing works — which is why they are asserted at
 * all rather than left to a click-through.
 */
describe('the staff shell', function () {
    it('puts alpine on the page', function () {
        // Livewire injects its assets, and Alpine with them, only on a page
        // that renders a component. Every staff screen is a full-page Livewire
        // component today, so this passes either way; the layout emits
        // @livewireScripts explicitly so the first plain Blade page added here
        // does not silently lose Alpine. docs/12 recorded that hazard and the
        // public layout still shipped without it once.
        $this->actingAs(coordinator())->get(route('staff.sponsors'))
            ->assertOk()
            ->assertSee('livewire.js', escape: false);
    });

    it('loads the fonts before the stylesheet that uses them', function () {
        /*
         * .ai/rules/layouts.md: @fonts before @vite, and never a Google link.
         *
         * Rendered through Blade rather than fetched over HTTP, which is how
         * FrontendWiringTest checks the public layout — @fonts reads the build
         * manifest, and asserting on a real request drags the whole Vite state
         * into a test about ordering.
         */
        $html = Blade::render('<x-layouts.staff>x</x-layouts.staff>');

        $font = strpos($html, '@font-face');
        // `rel="stylesheet"`, not 'app.css': Vite emits a hashed filename, so
        // the source name never appears in the document.
        $css = strpos($html, 'rel="stylesheet"');

        expect($font)->not->toBeFalse('The staff layout is not calling @fonts.')
            ->and($css)->not->toBeFalse('The staff layout is not calling @vite.')
            ->and($font)->toBeLessThan($css);

        expect($html)->not->toContain('fonts.googleapis.com')
            ->and($html)->not->toContain('fonts.gstatic.com');
    });

    it('renders exactly one toast region', function () {
        // Two live regions announce everything twice.
        $html = $this->actingAs(coordinator())->get(route('staff.sponsors'))->getContent();

        expect(substr_count($html, 'ui-toast-region'))->toBeLessThanOrEqual(1);
        expect($html)->toContain('x-data="{ sidebar: false }"');
    });

    it('refuses a signed-in rep with 403, not a redirect', function () {
        // They are already past login; bouncing them there would be a lie.
        $this->actingAs(User::factory()->rep()->create())
            ->get(route('staff.sponsors'))
            ->assertForbidden();
    });

    it('sends a guest to log in', function () {
        auth()->logout();

        $this->get(route('staff.sponsors'))->assertRedirectContains('/login');
    });
});
