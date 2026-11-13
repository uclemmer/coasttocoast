<?php

use App\Models\Event;
use App\Models\FaqItem;
use Database\Seeders\ProductionSeeder;
use UClemmer\LaravelCore\Content\Content;

/*
 * The move from the Convention Center to The Westin (owner, 2026-11-10;
 * docs/29 §5 and §8).
 *
 * The seeders never overwrite an existing row, so the copy is moved twice:
 * in the seeders for a fresh database, and by the migration
 * `move_venue_copy_to_the_westin` for one that was already seeded. That
 * migration rewrites a row only while it holds the original seeded text.
 */

function westinMigration(): object
{
    return require database_path('migrations/2026_11_13_153933_move_venue_copy_to_the_westin.php');
}

describe('a fresh seed', function () {
    beforeEach(fn () => $this->seed(ProductionSeeder::class));

    it('answers "when and where" with The Westin', function () {
        $answer = FaqItem::query()->where('question', 'When and where is the fair?')->value('answer');

        expect($answer)->toContain('The Westin Chattanooga, 801 Pine Street')
            ->not->toContain('Convention')
            ->not->toContain('Carter');
    });

    it('no longer promises the Convention Center\'s parking or volunteers', function () {
        expect(Content::query()->where('slug', 'home.hero')->value('body'))
            ->not->toContain('parking')
            ->not->toContain('volunteers')
            ->toContain('exhibit table and the pre-fair dinner reception');
    });

    it('shows no old venue anywhere public once the 2027 fair is published', function (string $path) {
        // Until then the site is about the most recent published fair, 2026,
        // which really was at the Convention Center. That is history, not a
        // stale reference.
        Event::query()->where('slug', 'college-fair-2027')->update(['is_published' => true]);

        $this->get($path)->assertOk()
            ->assertDontSee('Convention Center')
            ->assertDontSee('Carter Street')
            ->assertDontSee('complimentary parking');
    })->with(['/', '/faq']);

    it('puts The Westin\'s address in the FAQ', function () {
        $this->get('/faq')->assertOk()->assertSee('801 Pine Street');
    });
});

describe('a database seeded before the move', function () {
    it('rewrites the original Convention Center answer and hero', function () {
        $migration = westinMigration();

        $faq = FaqItem::factory()->create(['question' => 'When and where is the fair?', 'answer' => $migration::OLD_FAQ_ANSWER]);
        $this->seed(ProductionSeeder::class);
        // The seeder skipped the existing FAQ row; force the hero back to its
        // pre-move text, as an older database would hold it (with CRLF, as a
        // Windows checkout can seed it).
        Content::query()->where('slug', 'home.hero')->update(['body' => str_replace("\n", "\r\n", $migration::OLD_HERO)]);

        $migration->up();

        expect($faq->refresh()->answer)->toBe($migration::NEW_FAQ_ANSWER)
            ->and(Content::query()->where('slug', 'home.hero')->value('body'))->toBe($migration::NEW_HERO);
    });

    it('leaves an answer someone has already edited alone', function () {
        $edited = 'We are at The Westin this year; park in the hotel garage.';
        $faq = FaqItem::factory()->create(['question' => 'When and where is the fair?', 'answer' => $edited]);

        westinMigration()->up();

        expect($faq->refresh()->answer)->toBe($edited);
    });

    it('can be rolled back to the old text', function () {
        $migration = westinMigration();
        $faq = FaqItem::factory()->create(['question' => 'When and where is the fair?', 'answer' => $migration::OLD_FAQ_ANSWER]);

        $migration->up();
        $migration->down();

        expect($faq->refresh()->answer)->toBe($migration::OLD_FAQ_ANSWER);
    });
});
