{{--
    Long-form copy from a laravel-core content block or a markdown FAQ answer.

    Tailwind's preflight strips heading and list styling, so rendered markdown
    needs explicit typography or it lands as a wall of identical lines — the
    exact fault the Filament build hit (doc 10, D-8-b). These are the
    handoff's body values: 16–17px, 1.7 line height, ink-700.

    The content is already-escaped HTML from the renderer, hence the raw echo.
--}}
@props(['html' => null])

<div {{ $attributes->class([
    'max-w-[62ch] text-[17px] leading-[1.7] text-ink-700',
    '[&_h2]:font-display [&_h2]:text-[clamp(22px,2.4vw,28px)] [&_h2]:font-extrabold [&_h2]:uppercase [&_h2]:leading-tight [&_h2]:text-ink-900',
    '[&_h2]:mt-10 [&_h2]:mb-3 [&_h2:first-child]:mt-0',
    '[&_h3]:font-display [&_h3]:text-[19px] [&_h3]:font-bold [&_h3]:text-ink-900 [&_h3]:mt-8 [&_h3]:mb-2',
    '[&_p]:mb-4 [&_p:last-child]:mb-0',
    '[&_ul]:mb-4 [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:ps-5 [&_li]:mb-1.5',
    '[&_a]:text-brand-600 [&_a]:underline [&_a]:underline-offset-[3px] [&_a:hover]:text-brand-700',
    '[&_strong]:font-semibold [&_strong]:text-ink-900',
    '[&_blockquote]:border-s-4 [&_blockquote]:border-brand-600 [&_blockquote]:bg-brand-100 [&_blockquote]:px-5 [&_blockquote]:py-4',
]) }}>{!! $html ?? $slot !!}</div>
