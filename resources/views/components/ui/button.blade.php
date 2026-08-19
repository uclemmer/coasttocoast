{{--
    The button, in the five variants the handoff documents.

    The padding difference between primary and secondary is not a typo: the
    secondary has a 2px border, so it carries 12px/24px against the primary's
    14px/26px to end up the same height. The handoff calls that out explicitly.

    Renders an <a> when given an `href` and a <button> otherwise, so a link
    that looks like a button is still a link.
--}}
@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
])

@php
    $base = 'inline-flex items-center justify-center whitespace-nowrap rounded-md font-display font-bold uppercase tracking-[0.04em] transition-colors';

    $variants = [
        'primary' => 'bg-brand-600 px-[26px] py-3.5 text-sm text-white hover:bg-brand-700',
        'secondary' => 'border-2 border-brand-600 bg-transparent px-6 py-3 text-sm text-brand-650 hover:bg-brand-50',
        'ghost' => 'bg-transparent px-[26px] py-3.5 text-sm text-brand-650 hover:bg-brand-50',
        'danger' => 'bg-danger px-[26px] py-3.5 text-sm text-white hover:bg-danger-dark',
        // White on the green panel, and over the hero photo.
        'on-green' => 'bg-white px-7 py-[15px] text-sm text-brand-600 hover:bg-brand-50',
        'on-photo' => 'border-2 border-white bg-white/95 px-[22px] py-3 text-[13.5px] text-brand-650 shadow-over-photo hover:bg-white',
        'on-photo-solid' => 'bg-brand-600 px-[22px] py-3 text-[13.5px] text-white shadow-over-photo hover:bg-brand-700',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}"
            {{ $attributes->class($classes.' disabled:cursor-not-allowed disabled:bg-disabled-bg disabled:text-ink-300 disabled:hover:bg-disabled-bg') }}>
        {{ $slot }}
    </button>
@endif
