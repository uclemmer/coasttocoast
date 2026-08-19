{{--
    The Caveat script line that introduces almost every section.

    `tone` picks the colour for its background: `green` on white, `light` on the
    green panel, `hero` over the photograph (where it is also rotated, per the
    design).
--}}
@props(['tone' => 'green'])

@php
    $tones = [
        'green' => 'text-brand-600',
        'light' => 'text-brand-300',
        'hero' => 'text-brand-500 -rotate-2 text-over-photo-sm',
    ];
@endphp

<p {{ $attributes->class(['font-script font-bold', $tones[$tone] ?? $tones['green']]) }}>{{ $slot }}</p>
