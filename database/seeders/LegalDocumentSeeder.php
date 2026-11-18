<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use UClemmer\LaravelLegal\Documents;
use UClemmer\LaravelLegal\LegalService;

/**
 * The Terms of Service and the Privacy Policy, as first drafts (docs/31).
 *
 * Each document gets the live site's URL (`/legal/privacy-policy`,
 * `/legal/terms-of-service`) and, if it has never had a version, a DRAFT
 * from this fair's own starter in `resources/stubs/legal`.
 *
 * It never publishes. A published version is permanent, and these drafts
 * carry TODO markers and a review notice on purpose: publishing is the
 * owner's act, in /admin, after the text has been checked. Until then the
 * public pages 404, which is the package's answer to an empty policy.
 *
 * Idempotent, and careful about what it touches: a document that already has
 * any version is left alone (the package's `draft()` would overwrite a draft
 * someone is writing), and a slug is changed only while it is still the
 * package's default.
 */
class LegalDocumentSeeder extends Seeder
{
    /** @var array<string, array{default: string, slug: string}> */
    public const SLUGS = [
        Documents::PRIVACY => ['default' => 'privacy', 'slug' => 'privacy-policy'],
        Documents::TERMS => ['default' => 'terms', 'slug' => 'terms-of-service'],
    ];

    public function run(LegalService $legal): void
    {
        foreach (self::SLUGS as $key => $slugs) {
            $document = $legal->document($key);

            if ($document->slug === $slugs['default']) {
                $document->update(['slug' => $slugs['slug']]);
            }

            $starter = $legal->starter($key);

            if ($starter !== null && $document->versions()->doesntExist()) {
                $legal->draft($key, $starter, __('First draft, written from how the fair\'s site works (docs/31).'));
            }
        }
    }
}
