<?php

namespace App\Filament\Site\Concerns;

use Filament\Schemas\Components\Text;
use Illuminate\Support\HtmlString;
use UClemmer\LaravelCore\Content\ContentType;
use UClemmer\LaravelCore\Content\Renderer;

/**
 * Page copy, from laravel-core's Content module (doc 03).
 *
 * Every public page's prose is an editable `block` rather than a string in a
 * template, so the coordinator can fix a typo without a deploy. This turns one
 * into something a Filament schema can render.
 *
 * A missing block renders as nothing at all. Not a placeholder, not an error:
 * a half-seeded database or a block somebody archived should leave a page with
 * one less paragraph, not a page with "content.missing" on it in front of a
 * hundred colleges.
 */
trait RendersContentBlocks
{
    protected function block(string $slug): ?Text
    {
        $html = app(Renderer::class)->renderSlug($slug, ContentType::Block);

        if (! $html instanceof HtmlString || blank($html->toHtml())) {
            return null;
        }

        return Text::make($html)->key('block-'.$slug);
    }

    /**
     * Several blocks in order, with the missing ones dropped.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, Text>
     */
    protected function blocks(array $slugs): array
    {
        return array_values(array_filter(array_map(
            fn (string $slug): ?Text => $this->block($slug),
            $slugs,
        )));
    }
}
