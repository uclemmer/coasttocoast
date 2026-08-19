{{--
    The alert, in the three variants the handoff documents: a 1px tinted border
    with a 4px coloured left border, a Montserrat 700 14px title and 15px body.
--}}
@props(['variant' => 'success', 'title' => null])

@php
    $variants = [
        'success' => 'border-brand-200 border-s-brand-600 bg-brand-50 text-ink-700',
        'warning' => 'border-warn-200 border-s-warn bg-warn-50 text-warn-dark',
        'danger' => 'border-danger-200 border-s-danger bg-danger-50 text-danger-dark',
    ];
@endphp

<div role="status"
     {{ $attributes->class(['rounded-lg border border-s-4 px-[18px] py-3.5', $variants[$variant] ?? $variants['success']]) }}>
    @if ($title)
        <p class="font-display text-sm font-bold">{{ $title }}</p>
    @endif
    <div class="text-[15px] leading-[1.6] @if ($title) mt-1 @endif">{{ $slot }}</div>
</div>
