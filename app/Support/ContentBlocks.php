<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use UClemmer\LaravelCore\Content\ContentType;
use UClemmer\LaravelCore\Content\Renderer;

/**
 * Page copy, from laravel-core's Content module (doc 03).
 *
 * Every piece of prose on the public site is an editable `block` rather than a
 * string in a template, so the coordinator can fix a typo without a deploy.
 *
 * A missing block renders as **nothing at all**. Not a placeholder, not an
 * error: a half-seeded database, or a block somebody archived, should leave a
 * page one paragraph short rather than printing `content.missing` in front of a
 * hundred colleges. This behaviour carried over from the Filament build
 * (doc 10, D-5.2-a) and is worth keeping.
 *
 * Replaces the `RendersContentBlocks` trait, which returned Filament schema
 * components; a Blade view wants HTML.
 */
final class ContentBlocks
{
    /**
     * The rendered block, or null when there is nothing to show.
     */
    public static function render(string $slug): ?HtmlString
    {
        $html = app(Renderer::class)->renderSlug($slug, ContentType::Block);

        if (! $html instanceof HtmlString || blank(trim($html->toHtml()))) {
            return null;
        }

        return $html;
    }

    /**
     * Several blocks at once, keyed by slug, with the empty ones dropped.
     *
     * @param  array<int, string>  $slugs
     * @return array<string, HtmlString>
     */
    public static function renderMany(array $slugs): array
    {
        $blocks = [];

        foreach ($slugs as $slug) {
            $html = self::render($slug);

            if ($html instanceof HtmlString) {
                $blocks[$slug] = $html;
            }
        }

        return $blocks;
    }
}
