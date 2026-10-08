<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Staff-written Markdown, rendered with raw HTML escaped.
 *
 * Every place this app turns typed Markdown into HTML goes through here: the
 * public FAQ page, the FAQ editor's preview, campaign emails and the staff
 * view of a campaign. One renderer means the preview cannot drift from what a
 * visitor or recipient sees, which is why the editor's preview exists.
 *
 * Laravel's `Str::markdown()` does NOT escape raw HTML by default. Measured
 * 2026-10-07 on league/commonmark 2.10.3: `<div>`, `<img onerror=…>` and a
 * `javascript:` link all rendered live, and only the short DisallowedRawHtml
 * list (`<script>` among it) was neutralised — the filter a 2026 advisory
 * showed could be bypassed. The authors are trusted staff, but a pasted
 * fragment reached the public FAQ and recipients' inboxes as live HTML
 * (docs/24 §5). So:
 *
 *   html_input: escape         raw HTML is shown as text, not rendered
 *   allow_unsafe_links: false  `javascript:`, `vbscript:`, `file:` and
 *                              non-image `data:` URLs are dropped from links
 *
 * Markdown itself — emphasis, lists, links, headings, tables — is unchanged.
 * Nothing seeded or in the dev database used HTML when this was made; an
 * answer that ever needs a map or other embed should get a field of its own
 * rather than a hole in this.
 */
final class Markdown
{
    public static function render(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
