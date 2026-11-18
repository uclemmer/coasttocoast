<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace two TODO-OWNER FAQ answers with what the live site says
 * (2026-11-17; docs/29 §9).
 *
 * `FaqSeeder` never overwrites an existing row, so a database seeded before
 * this carries the placeholders. This rewrites each answer **only while it
 * still holds the placeholder**, so an answer already written in /staff/faq
 * is left alone. Same shape as `move_venue_copy_to_the_westin`.
 *
 * The W-9 answer promises a link "below this answer". That link appears once
 * the PDF is uploaded as the question's attachment, which happens in /staff,
 * not here: a signed W-9 carries the fair's EIN and stays out of git.
 */
return new class extends Migration
{
    public const W9_QUESTION = 'Can we get a W-9?';

    public const OLD_W9 = 'Yes. TODO-OWNER: upload the current signed W-9 as this question\'s attachment '
        .'(Staff → FAQ → edit this question → Attachment). A download link appears '
        .'under this answer once it is there, and this sentence can be replaced.';

    public const NEW_W9 = 'Yes. Download our W-9 from the link below this answer.';

    public const GUIDELINES_QUESTION = 'What are the guidelines for representatives at the fair?';

    public const OLD_GUIDELINES = 'TODO-OWNER: transcribe the fair conduct guidelines from the current site.';

    public const NEW_GUIDELINES = "These apply to everyone at the fair, exhibitors and visitors alike.\n\n"
        ."- **No recording or live streaming.** To protect the privacy of attendees and exhibitors, recording or live streaming of the event is not permitted.\n"
        ."- **No solicitation.** Soliciting or distributing promotional materials outside of designated exhibitor booths is prohibited.\n"
        ."- **Respectful behavior.** All attendees are expected to behave respectfully towards exhibitors and other attendees. Any form of harassment or disruptive behavior will not be tolerated.\n"
        ."- **Follow the event schedule.** Please adhere to the event schedule and attend sessions on time to avoid disruptions.\n"
        ."- **Keep aisles clear.** Ensure that aisles and walkways are kept clear for easy movement and access.\n"
        .'- **Follow the venue\'s rules.** Follow all rules and regulations set by the venue, including any safety protocols.';

    public function up(): void
    {
        $this->swap(self::W9_QUESTION, self::OLD_W9, self::NEW_W9);
        $this->swap(self::GUIDELINES_QUESTION, self::OLD_GUIDELINES, self::NEW_GUIDELINES);
    }

    public function down(): void
    {
        $this->swap(self::W9_QUESTION, self::NEW_W9, self::OLD_W9);
        $this->swap(self::GUIDELINES_QUESTION, self::NEW_GUIDELINES, self::OLD_GUIDELINES);
    }

    protected function swap(string $question, string $from, string $to): void
    {
        if (! Schema::hasTable('faq_items')) {
            return;
        }

        $normalise = fn (?string $text): string => trim(str_replace("\r\n", "\n", (string) $text));

        DB::table('faq_items')->where('question', $question)->get(['id', 'answer'])
            ->filter(fn (object $row): bool => $normalise($row->answer) === $normalise($from))
            ->each(fn (object $row) => DB::table('faq_items')->where('id', $row->id)->update(['answer' => $to]));
    }
};
