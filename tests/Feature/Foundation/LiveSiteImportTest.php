<?php

use App\Models\FaqItem;
use App\Models\Sponsor;
use Database\Seeders\ProductionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use UClemmer\LaravelCore\Content\Content;

/*
 * What was imported from the live site on 2026-11-17 (docs/29 §9): the fair
 * guidelines and the W-9 answer in the FAQ, every sponsor's counseling staff,
 * and the one testimonial. The FAQ answers also reach an already-seeded
 * database through `import_live_site_faq_answers`, only while they still hold
 * their TODO-OWNER placeholders.
 */

function liveImportMigration(): object
{
    return require database_path('migrations/2026_11_17_202119_import_live_site_faq_answers.php');
}

describe('a fresh seed', function () {
    beforeEach(fn () => $this->seed(ProductionSeeder::class));

    it('lists all six fair guidelines in the FAQ', function () {
        $answer = FaqItem::query()->where('question', 'What are the guidelines for representatives at the fair?')->value('answer');

        expect($answer)->not->toContain('TODO-OWNER')
            ->toContain('No recording or live streaming', 'No solicitation', 'Respectful behavior',
                'Follow the event schedule', 'Keep aisles clear', "Follow the venue's rules");

        $this->get('/faq')->assertOk()
            ->assertSeeInOrder(['No recording or live streaming', 'No solicitation', 'Keep aisles clear'])
            ->assertSee('<li>', escape: false);
    });

    it('leaves no TODO-OWNER in any FAQ answer that the live site could answer', function () {
        expect(FaqItem::query()->where('question', 'Can we get a W-9?')->value('answer'))
            ->toBe('Yes. Download our W-9 from the link below this answer.');
    });

    it('seeds every sponsor\'s counseling staff, in the live site\'s order', function () {
        $staff = Sponsor::query()->with('staff')->get()->mapWithKeys(
            fn (Sponsor $sponsor): array => [$sponsor->name => $sponsor->staff->pluck('name')->all()],
        );

        expect($staff['Baylor School'])->toBe(['Ann Katherine Taylor', 'Liz Young', 'Meg Conner', 'Jake Kennedy', "Kelly O'Mara"])
            ->and($staff['Girls Preparatory School'])->toBe(['Andrew Reich', 'Anders Swanson'])
            ->and($staff['McCallie School'])->toBe(['Abbie Roberts', 'Megan Cooper', 'Erik Johnson', 'Alex McCallie'])
            ->and($staff['St. Andrews-Sewanee School'])->toBe(['Bob MacLellan']);

        $this->get('/sponsors')->assertOk()
            ->assertSee('Abbie Roberts')
            ->assertSee('Director of College Counseling');
    });

    it('shows the testimonial on the home page', function () {
        $this->get('/')->assertOk()
            ->assertSee('id="testimonial"', escape: false)
            ->assertSee('beyond their wildest dreams')
            ->assertSee('Ann Katherine Taylor')
            ->assertDontSee('Coast-to –Coast');
    });

    it('drops the testimonial section when the block is blanked', function () {
        Content::query()->where('slug', 'home.testimonial')->update(['body' => '']);

        $this->get('/')->assertOk()->assertDontSee('id="testimonial"', escape: false);
    });

    it('offers the W-9 once it is uploaded as the answer\'s attachment', function () {
        Storage::fake(FaqItem::ATTACHMENT_DISK);
        $item = FaqItem::query()->where('question', 'Can we get a W-9?')->firstOrFail();
        $path = UploadedFile::fake()->create('C2C-W9-2022.pdf', 300, 'application/pdf')
            ->store(FaqItem::ATTACHMENT_DIRECTORY, FaqItem::ATTACHMENT_DISK);
        $item->update(['attachment_path' => $path, 'attachment_name' => 'C2C-W9-2022.pdf']);

        $this->get('/faq')->assertOk()->assertSee('C2C-W9-2022.pdf');
    });
});

describe('a database seeded before the import', function () {
    it('replaces both placeholders', function () {
        $migration = liveImportMigration();
        $w9 = FaqItem::factory()->create(['question' => $migration::W9_QUESTION, 'answer' => $migration::OLD_W9]);
        $rules = FaqItem::factory()->create(['question' => $migration::GUIDELINES_QUESTION, 'answer' => str_replace("\n", "\r\n", $migration::OLD_GUIDELINES)]);

        $migration->up();

        expect($w9->refresh()->answer)->toBe($migration::NEW_W9)
            ->and($rules->refresh()->answer)->toBe($migration::NEW_GUIDELINES);
    });

    it('leaves an answer someone has already written alone', function () {
        $written = 'Be kind, keep the aisles clear, and no filming.';
        $rules = FaqItem::factory()->create(['question' => liveImportMigration()::GUIDELINES_QUESTION, 'answer' => $written]);

        liveImportMigration()->up();

        expect($rules->refresh()->answer)->toBe($written);
    });

    it('can be rolled back to the placeholders', function () {
        $migration = liveImportMigration();
        $w9 = FaqItem::factory()->create(['question' => $migration::W9_QUESTION, 'answer' => $migration::OLD_W9]);

        $migration->up();
        $migration->down();

        expect($w9->refresh()->answer)->toBe($migration::OLD_W9);
    });
});

it('compiles the testimonial\'s classes into the built stylesheet', function () {
    // Tailwind only emits classes it finds in a scanned file. If home.blade.php
    // stopped being scanned, or a class were mistyped, the quote would render
    // unstyled with no error anywhere. CI runs `npm run build` before Pest.
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    $css = (string) file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));

    expect($css)->toContain('.italic{')
        ->toContain('.text-\[18px\]')
        ->toContain('.\[\&_strong\]\:not-italic strong{font-style:normal}');
});
