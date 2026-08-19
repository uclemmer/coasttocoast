<?php

namespace App\Filament\Site\Concerns;

use Filament\Schemas\Components\Html;
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
 * `Html` rather than `Text`, and wrapped in `fi-prose`. `Text` renders a
 * `<span>`, which is invalid around the `<h2>` and `<p>` that markdown
 * produces and — more visibly — inherits none of the typography: a rendered
 * block came out as a wall of identically-sized lines with no spacing.
 * `fi-prose` is Filament's own typography class, so the public pages get
 * headings, paragraphs and lists that match the rest of the product.
 *
 * A missing block renders as nothing at all. Not a placeholder, not an error:
 * a half-seeded database or a block somebody archived should leave a page with
 * one less paragraph, not a page with "content.missing" on it in front of a
 * hundred colleges.
 */
trait RendersContentBlocks
{
    protected function block(string $slug): ?Html
    {
        $html = app(Renderer::class)->renderSlug($slug, ContentType::Block);

        if (! $html instanceof HtmlString || blank(trim($html->toHtml()))) {
            return null;
        }

        return static::prose($html->toHtml())->key('block-'.$slug);
    }

    /**
     * Several blocks in order, with the missing ones dropped.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, Html>
     */
    protected function blocks(array $slugs): array
    {
        return array_values(array_filter(array_map(
            fn (string $slug): ?Html => $this->block($slug),
            $slugs,
        )));
    }

    /**
     * Any trusted HTML, given Filament's typography.
     *
     * Static so the pages that render markdown from somewhere other than a
     * content block — the FAQ's answers — get the same treatment from the same
     * place.
     */
    public static function prose(string $html): Html
    {
        return Html::make(new HtmlString('<div class="fi-prose max-w-none">'.$html.'</div>'));
    }
}
