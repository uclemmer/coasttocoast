{{--
    The uppercase Montserrat 800 heading that follows an eyebrow.

    Fluid via clamp(), as the design specifies: 26px at the smallest, 36px at
    the largest. `level` keeps the document outline honest on pages where this
    is not the top heading.
--}}
@props(['level' => 'h2', 'tone' => 'ink'])

<{{ $level }}
    {{ $attributes->class([
        'font-display font-extrabold uppercase leading-tight text-[clamp(26px,3vw,36px)]',
        'text-ink-900' => $tone === 'ink',
        'text-white' => $tone === 'light',
    ]) }}>{{ $slot }}</{{ $level }}>
