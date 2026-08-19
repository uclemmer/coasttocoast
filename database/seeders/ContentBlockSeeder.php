<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use UClemmer\LaravelCore\Content\Content;
use UClemmer\LaravelCore\Content\ContentFormat;
use UClemmer\LaravelCore\Content\ContentStatus;
use UClemmer\LaravelCore\Content\ContentType;

/**
 * The editable page copy, as laravel-core Content rows of type `block`
 * (doc 03 — this app has no `content_blocks` table of its own).
 *
 * The text is transcribed from the current site (doc 00) so the rebuild launches
 * saying what the fair already says. Every block is editable in the admin panel
 * with revision history, so this seeder is a starting point, never the authority.
 *
 * Idempotent by slug: re-running does NOT overwrite an edited block, and does
 * not resurrect one the coordinator deleted. A seeder that clobbered their
 * wording every deploy would be worse than no seeder at all.
 */
class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->blocks() as $slug => $block) {
            // withTrashed(), deliberately. Content soft-deletes, but its unique
            // index is (type, slug) and does not include deleted_at -- so a
            // block the coordinator has deleted still occupies the slug. Asking
            // the default scope would report it missing, and the insert would
            // then die on a UNIQUE constraint and take the whole deploy's seed
            // down with it.
            if (Content::query()->withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            Content::query()->create([
                'type' => ContentType::Block,
                'slug' => $slug,
                'title' => $block['title'],
                'body' => $block['body'],
                'format' => ContentFormat::Markdown,
                'status' => ContentStatus::Published,
                'published_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, array{title: string, body: string}>
     */
    protected function blocks(): array
    {
        return [
            'home.hero' => [
                'title' => 'Home — hero paragraph',
                'body' => <<<'MD'
                    Each spring, more than one hundred colleges and universities meet the sophomores,
                    juniors, and parents of Tennessee's tri-state area in a single evening.
                    Registration includes your exhibit table, the pre-fair dinner reception,
                    complimentary parking, and student volunteers to carry your materials in.
                    MD,
            ],

            'home.for_representatives' => [
                'title' => 'Home — what registration includes',
                'body' => <<<'MD'
                    Register online and we will add you to our mailing list and follow up with full
                    details. Institutions paying by check may print the completed registration form
                    and post it with the computed fees.
                    MD,
            ],

            'about.body' => [
                'title' => 'About — body',
                'body' => <<<'MD'
                    The Coast to Coast College Fair is an annual college fair held in Chattanooga,
                    Tennessee. It is organized by the college counseling offices of four sponsoring
                    preparatory schools and has run for close to twenty years.

                    ### Who it is for

                    High school sophomores and juniors, and their parents. Students meet admissions
                    representatives from more than a hundred colleges and universities in a single
                    evening, with no appointments and no cost.

                    ### Financial aid workshop

                    A financial aid workshop runs alongside the fair for families who want help
                    understanding what college actually costs and how aid is awarded.

                    ### Counselor reception

                    Visiting representatives are invited to a reception with local college counselors
                    before the fair opens to the public.
                    MD,
            ],

            'representatives.intro' => [
                'title' => 'Representatives page — intro',
                'body' => <<<'MD'
                    The colleges and universities below have confirmed their place at this year's fair.
                    The list grows as registrations are confirmed.
                    MD,
            ],

            'last_year.intro' => [
                'title' => 'Last Year page — intro',
                'body' => <<<'MD'
                    These colleges and universities attended our most recent fair. It is a good guide
                    to who you can expect to meet this year, but it is not this year's list.
                    MD,
            ],

            'sponsors.intro' => [
                'title' => 'Sponsors page — intro',
                'body' => <<<'MD'
                    The fair is organized and underwritten by the college counseling offices of four
                    Chattanooga preparatory schools.
                    MD,
            ],

            'contact.intro' => [
                'title' => 'Contact page — intro',
                'body' => <<<'MD'
                    Questions about registering, payment, or the fair itself? Send us a note and the
                    fair coordinator will get back to you.
                    MD,
            ],

            // TODO-OWNER: doc 01 lists the refund and cancellation policy as an
            // open question. This placeholder is deliberately non-committal —
            // it must be replaced with the real policy before the first live
            // payment, and it is a content block precisely so that can happen
            // without a deploy. See docs/10-implementation-decisions.md.
            'policy.refunds' => [
                'title' => 'Refund and cancellation policy — TODO-OWNER',
                'body' => <<<'MD'
                    **This copy is a placeholder and must be replaced before registration opens.**

                    To cancel a registration or ask about a refund, contact the fair coordinator using
                    the details on our contact page. Refund requests are considered individually.
                    MD,
            ],

            'registration.check_instructions' => [
                'title' => 'Check payment instructions',
                'body' => <<<'MD'
                    Print the registration form attached to this message, and mail it with your check
                    made payable to **Coast to Coast College Fair**.

                    Your place is held as soon as you register, and confirmed once the check arrives.
                    MD,
            ],
        ];
    }
}
