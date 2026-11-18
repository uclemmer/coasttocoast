<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use Illuminate\Database\Seeder;

/**
 * The public FAQ, transcribed from the current site (doc 00).
 *
 * The live site's FAQ answers date/time/venue, directions with a map embed,
 * how to register and pay, parking, hotels, the W-9 download and conduct
 * guidelines. Those it can answer from doc 00 are seeded verbatim in substance;
 * the rest carry a TODO-OWNER marker rather than invented detail, because a
 * confidently wrong parking answer is worse than an obviously missing one.
 *
 * Idempotent by question text.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $order => [$question, $answer]) {
            FaqItem::query()->firstOrCreate(
                ['question' => $question],
                ['answer' => $answer, 'sort_order' => $order, 'is_published' => true],
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    protected function items(): array
    {
        return [
            [
                'When and where is the fair?',
                // The Westin since 2027 (owner, 2026-11-10; docs/29). The
                // migration `move_venue_copy_to_the_westin` rewrites an existing
                // row that still carries the old Convention Center answer.
                'The fair is held at The Westin Chattanooga, 801 Pine Street, '
                    .'Chattanooga, TN 37402. The counselor reception runs from 5:00 to 6:30 PM and the fair '
                    .'is open to students and families from 6:30 to 8:00 PM. See the event page for this '
                    ."year's date.",
            ],
            [
                'Who should attend?',
                'The fair is aimed at high school sophomores and juniors and their parents. Admission is '
                    .'free and no registration is needed for students or families.',
            ],
            [
                'How does a college register?',
                'Create an account in the representative portal, then register your institution for the '
                    .'current fair. You can pay online by card, or print a registration form and mail it '
                    .'with a check.',
            ],
            [
                'What does registration cost?',
                'The registration fee is set per fair and shown on the event page before you commit. It '
                    .'covers your table at the fair and admission to the counselor reception beforehand.',
            ],
            [
                'Can we pay by check?',
                'Yes. Choose "check by mail" during registration and we will email you a printable form '
                    .'with the mailing address. Your place is held from the moment you register and '
                    .'confirmed when the check arrives.',
            ],
            [
                'Do you offer financial assistance with the registration fee?',
                'Colleges that need help with the fee can apply for a grant from the representative '
                    .'portal when registration is open. The fair coordinator reviews each application '
                    .'and will let you know either way by email.',
            ],
            [
                'Can we get a W-9?',
                // The PDF itself is uploaded through Staff → FAQ → this question →
                // Attachment, not seeded: a signed W-9 carries the fair's EIN,
                // so it stays out of git (docs/29 §9).
                'Yes. Download our W-9 from the link below this answer.',
            ],
            [
                'Where do we park, and where do we unload?',
                'TODO-OWNER: transcribe the parking and unloading directions from the current site.',
            ],
            [
                'Are there hotels nearby?',
                'TODO-OWNER: transcribe the recommended hotel list from the current site.',
            ],
            [
                'What are the guidelines for representatives at the fair?',
                // From the live site's FAQ, 2026-11-17 (docs/29 §9).
                "These apply to everyone at the fair, exhibitors and visitors alike.\n\n"
                    ."- **No recording or live streaming.** To protect the privacy of attendees and exhibitors, recording or live streaming of the event is not permitted.\n"
                    ."- **No solicitation.** Soliciting or distributing promotional materials outside of designated exhibitor booths is prohibited.\n"
                    ."- **Respectful behavior.** All attendees are expected to behave respectfully towards exhibitors and other attendees. Any form of harassment or disruptive behavior will not be tolerated.\n"
                    ."- **Follow the event schedule.** Please adhere to the event schedule and attend sessions on time to avoid disruptions.\n"
                    ."- **Keep aisles clear.** Ensure that aisles and walkways are kept clear for easy movement and access.\n"
                    .'- **Follow the venue\'s rules.** Follow all rules and regulations set by the venue, including any safety protocols.',
            ],
            [
                'Will we hear from you before the fair?',
                'Yes. Registered colleges are added to the mailing list for that fair and receive '
                    .'logistics details — check-in, shipping materials and parking — before the date.',
            ],
        ];
    }
}
