{{--
    The one shell behind every full-screen page this app serves when there is
    nothing else to show: the four error views and the maintenance page
    (design handoff, "Error Pages.dc.html" and "Maintenance Page.dc.html" —
    they are the same picture, and the design draws them that way).

    SELF-CONTAINED ON PURPOSE — no <x-layouts.app>, no @vite, no database, no
    `route()`. Two separate reasons, and both matter:

      * `php artisan down --render=errors::503` renders this ONCE and serves the
        resulting HTML straight out of storage/framework/down for the whole
        outage — which is exactly when public/build is being replaced. An @vite
        call would bake that moment's asset hash into the file and the page
        would 404 its own stylesheet.
      * 500.blade.php renders while something is already broken. If what broke
        was the asset build, the stylesheet, or a service provider, an error
        page that depends on any of them fails to render its own failure.

    So the styles are inline, the images are static paths under public/, and the
    fonts fall back to the system stack rather than the site's self-hosted
    faces. Config is still readable (bootstrapping happens before the
    maintenance middleware), but note that a prerendered page freezes whatever
    it read until the next `artisan down`.

    NO DARK OVERLAY on the cityscape, deliberately — the same rule as the
    landing hero. Legibility comes from the layered text shadows on every text
    element. Removing them is not a cosmetic change.

    Props:
      $code      the status code, or null for the maintenance page. Its
                 presence is what switches the two sets of proportions the
                 design draws: with a code the wordmark is smaller and the
                 heading steps down to make room for the giant numeral.
      $script    the Caveat line above the heading
      $heading   the uppercase H1
      $title     the <title>; falls back to the heading
      $slot      the body copy
      $actions   the button row
--}}
@props([
    'code' => null,
    'script',
    'heading',
    'title' => null,
    'actions' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ ($title ?? $heading) . ' — ' . config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: #22302a; color: #22302a; overflow-x: clip;
               font-family: 'Source Sans 3', Georgia, 'Times New Roman', serif; text-wrap: pretty; }
        .wrap { min-height: 100vh; display: grid; }
        .stage { position: relative; display: grid; align-content: center; justify-items: center;
                 text-align: center; padding: clamp(48px, 8vw, 96px) clamp(20px, 5vw, 64px); overflow: hidden; }
        .photo { position: absolute; inset: 0; width: 100%; height: 100%;
                 object-fit: cover; object-position: center 40%; filter: saturate(1.15); }
        .panel { position: relative; max-width: 760px; display: grid; justify-items: center; }
        .wordmark { width: min(360px, 80vw); height: auto; display: block;
                    border-radius: 8px; box-shadow: 0 6px 28px rgba(8, 24, 14, .45); }
        .has-code .wordmark { width: min(300px, 70vw); }
        .code { font-family: Montserrat, 'Segoe UI', system-ui, sans-serif; font-weight: 800;
                font-size: clamp(96px, 16vw, 170px); line-height: 1; color: #fff; margin-top: 28px;
                letter-spacing: -.02em;
                text-shadow: 0 2px 4px rgba(8, 24, 14, .85), 0 10px 40px rgba(8, 24, 14, .65); }
        .script { font-family: Caveat, 'Segoe Script', cursive; font-weight: 700;
                  font-size: clamp(28px, 3.4vw, 40px); color: #b8f0ca; margin: 36px 0 0;
                  transform: rotate(-2deg); text-shadow: 0 1px 3px rgba(8, 24, 14, .9), 0 3px 16px rgba(8, 24, 14, .7); }
        .has-code .script { font-size: clamp(26px, 3.2vw, 38px); margin-top: 6px; }
        h1 { font-family: Montserrat, 'Segoe UI', system-ui, sans-serif; font-weight: 800;
             font-size: clamp(30px, 4.2vw, 48px); line-height: 1.15; text-transform: uppercase;
             letter-spacing: -.01em; margin: 10px 0 0; color: #fff;
             text-shadow: 0 2px 4px rgba(8, 24, 14, .85), 0 6px 28px rgba(8, 24, 14, .65); }
        .has-code h1 { font-size: clamp(26px, 3.6vw, 42px); margin-top: 8px; }
        p.lede { font-size: 18px; line-height: 1.65; max-width: 52ch; margin: 20px 0 0; color: #fff;
                 text-shadow: 0 1px 3px rgba(8, 24, 14, .9), 0 3px 14px rgba(8, 24, 14, .7); }
        .has-code p.lede { margin-top: 18px; }
        .actions { display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; margin-top: 32px; }
        .has-code .actions { margin-top: 30px; }
        .btn { white-space: nowrap; text-decoration: none;
               font-family: Montserrat, 'Segoe UI', system-ui, sans-serif; font-weight: 700;
               font-size: 14px; letter-spacing: .04em; text-transform: uppercase;
               padding: 13px 26px; border-radius: 6px; }
        .btn-solid { background: rgba(255, 255, 255, .94); color: #146a37; border: 2px solid #fff;
                     box-shadow: 0 4px 18px rgba(0, 0, 0, .35); }
        .btn-solid:hover, .btn-solid:focus-visible { background: #fff; }
        .btn-ghost { background: rgba(8, 24, 14, .35); color: #fff; border: 2px solid rgba(255, 255, 255, .85); }
        .btn-ghost:hover, .btn-ghost:focus-visible { background: rgba(8, 24, 14, .55); }
        @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
    </style>
</head>
<body>
    <div class="wrap">
        <main class="stage">
            <img class="photo" src="/images/cityscape.jpg"
                 alt="{{ __('Aerial view of Chattanooga\'s riverfront and bridges') }}">

            <div @class(['panel', 'has-code' => $code !== null])>
                <img class="wordmark" src="/images/wordmark.jpg" alt="{{ config('app.name') }}">

                @if ($code !== null)
                    {{-- A decoration, not a heading: the sentence a screen
                         reader needs is the H1 below it, and announcing
                         "404" first is noise. --}}
                    <div class="code" aria-hidden="true">{{ $code }}</div>
                @endif

                <p class="script">{{ $script }}</p>
                <h1>{{ $heading }}</h1>
                <p class="lede">{{ $slot }}</p>

                @if ($actions !== null)
                    <div class="actions">{{ $actions }}</div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
