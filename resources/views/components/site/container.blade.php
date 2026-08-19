{{--
    The 1140px content column, with the design's 24px gutter.

    Every contained section on the site sits in one of these. Full-bleed bands
    deliberately do not.
--}}
<div {{ $attributes->class('mx-auto max-w-site px-6') }}>{{ $slot }}</div>
