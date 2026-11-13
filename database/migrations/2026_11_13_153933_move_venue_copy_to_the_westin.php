<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the seeded venue copy to The Westin (owner, 2026-11-10; docs/29).
 *
 * `FaqSeeder` and `ContentBlockSeeder` never overwrite an existing row, so
 * changing them reaches only a fresh database. This migration reaches one
 * that was already seeded. It rewrites the two rows that named the
 * Convention Center or its arrangements, and **only while they still hold
 * the original seeded text**. An answer someone has edited in /staff or
 * /admin is theirs, and is left exactly as it is.
 *
 * Query builder rather than models, so the migration keeps working whatever
 * the models become. Line endings are normalised before comparing, because a
 * Windows checkout can seed CRLF.
 */
return new class extends Migration
{
    public const OLD_FAQ_ANSWER = 'The fair is held at the Chattanooga Convention & Trade Center, 1150 Carter Street, '
        .'Chattanooga, TN 37402. The counselor reception runs from 5:00 to 6:30 PM and the fair '
        .'is open to students and families from 6:30 to 8:00 PM. See the event page for this '
        ."year's date.";

    public const NEW_FAQ_ANSWER = 'The fair is held at The Westin Chattanooga, 801 Pine Street, '
        .'Chattanooga, TN 37402. The counselor reception runs from 5:00 to 6:30 PM and the fair '
        .'is open to students and families from 6:30 to 8:00 PM. See the event page for this '
        ."year's date.";

    public const OLD_HERO = "Each spring, more than one hundred colleges and universities meet the sophomores,\n"
        ."juniors, and parents of Tennessee's tri-state area in a single evening.\n"
        ."Registration includes your exhibit table, the pre-fair dinner reception,\n"
        .'complimentary parking, and student volunteers to carry your materials in.';

    public const NEW_HERO = "Each spring, more than one hundred colleges and universities meet the sophomores,\n"
        ."juniors, and parents of Tennessee's tri-state area in a single evening.\n"
        .'Registration includes your exhibit table and the pre-fair dinner reception.';

    public function up(): void
    {
        $this->swap('faq_items', 'answer', 'question', 'When and where is the fair?', self::OLD_FAQ_ANSWER, self::NEW_FAQ_ANSWER);
        $this->swap('core_contents', 'body', 'slug', 'home.hero', self::OLD_HERO, self::NEW_HERO);
    }

    public function down(): void
    {
        $this->swap('faq_items', 'answer', 'question', 'When and where is the fair?', self::NEW_FAQ_ANSWER, self::OLD_FAQ_ANSWER);
        $this->swap('core_contents', 'body', 'slug', 'home.hero', self::NEW_HERO, self::OLD_HERO);
    }

    /**
     * Replace `$from` with `$to` in the row keyed `$key = $value`, only if the
     * column still holds `$from`.
     */
    protected function swap(string $table, string $column, string $key, string $value, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $normalise = fn (?string $text): string => trim(str_replace("\r\n", "\n", (string) $text));

        DB::table($table)->where($key, $value)->get(['id', $column])
            ->filter(fn (object $row): bool => $normalise($row->{$column}) === $normalise($from))
            ->each(fn (object $row) => DB::table($table)->where('id', $row->id)->update([$column => $to]));
    }
};
