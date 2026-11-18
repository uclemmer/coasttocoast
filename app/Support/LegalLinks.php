<?php

namespace App\Support;

use UClemmer\LaravelLegal\Documents;
use UClemmer\LaravelLegal\LegalDocument;

/**
 * The Terms of Service and Privacy Policy links, for documents that have a
 * version in force (docs/31).
 *
 * A link appears only once the owner has published the document in /admin.
 * A draft has no public page (the package 404s it), and a footer link to a
 * 404 is worse than no link.
 *
 * Reads the document rows directly rather than through `LegalService::document()`,
 * which creates the row it is asked for: the footer renders on every page and
 * must never write.
 */
class LegalLinks
{
    /**
     * @return array<string, string> link text => URL, in footer order
     */
    public static function published(): array
    {
        if (! config('legal.enabled') || ! config('legal.routes.enabled')) {
            return [];
        }

        $documents = LegalDocument::query()
            ->whereIn('key', [Documents::TERMS, Documents::PRIVACY])
            ->get()
            ->keyBy('key');

        $links = [];

        foreach ([Documents::TERMS, Documents::PRIVACY] as $key) {
            $document = $documents->get($key);

            if ($document instanceof LegalDocument && $document->versionInForce() !== null) {
                $links[$document->title] = route('legal.show', ['slug' => $document->slug]);
            }
        }

        return $links;
    }
}
