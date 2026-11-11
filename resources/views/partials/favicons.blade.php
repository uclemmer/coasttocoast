{{--
    The site icon on every page (docs/29): an SVG for current browsers, a
    PNG-in-ICO for the rest, and a 180px icon for iOS home screens. The mark is
    "C2C" in white on the brand green; core's /admin uses the same SVG through
    `core.admin.favicon`.
--}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
