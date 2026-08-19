{{--
    The maintenance page (design handoff, "Maintenance Page.dc.html").

    Deliberately self-contained: no <x-layouts.app>, no @vite, no database.
    `php artisan down --render=errors::503` renders this once and serves the
    resulting HTML straight out of storage/framework/down for the whole outage
    -- which is exactly when the site is being deployed and public/build is
    being replaced. An @vite call would bake that moment's asset hash into the
    file and the page would 404 its own stylesheet.

    So the styles are inline, the images are static paths under public/, and the
    fonts fall back to the system stack rather than the site's self-hosted
    faces. Config is still readable (bootstrapping happens before the
    maintenance middleware), but note that a prerendered page freezes whatever
    it read until the next `artisan down`.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Down for maintenance — Coast to Coast College Fair</title>
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
        .script { font-family: Caveat, 'Segoe Script', cursive; font-weight: 700;
                  font-size: clamp(28px, 3.4vw, 40px); color: #b8f0ca; margin: 36px 0 0;
                  transform: rotate(-2deg); text-shadow: 0 1px 3px rgba(8, 24, 14, .9), 0 3px 16px rgba(8, 24, 14, .7); }
        h1 { font-family: Montserrat, 'Segoe UI', system-ui, sans-serif; font-weight: 800;
             font-size: clamp(30px, 4.2vw, 48px); line-height: 1.15; text-transform: uppercase;
             letter-spacing: -.01em; margin: 10px 0 0; color: #fff;
             text-shadow: 0 2px 4px rgba(8, 24, 14, .85), 0 6px 28px rgba(8, 24, 14, .65); }
        p.lede { font-size: 18px; line-height: 1.65; max-width: 52ch; margin: 20px 0 0; color: #fff;
                 text-shadow: 0 1px 3px rgba(8, 24, 14, .9), 0 3px 14px rgba(8, 24, 14, .7); }
        .cta { white-space: nowrap; text-decoration: none; background: rgba(255, 255, 255, .94);
               color: #146a37; border: 2px solid #fff; font-family: Montserrat, 'Segoe UI', system-ui, sans-serif;
               font-weight: 700; font-size: 14px; letter-spacing: .04em; text-transform: uppercase;
               padding: 13px 26px; border-radius: 6px; box-shadow: 0 4px 18px rgba(0, 0, 0, .35); margin-top: 32px; }
        .cta:hover, .cta:focus-visible { background: #fff; }
        @media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
    </style>
</head>
<body>
    <div class="wrap">
        <main class="stage">
            <img class="photo" src="/images/cityscape.jpg"
                 alt="Aerial view of Chattanooga's riverfront and bridges">

            <div class="panel">
                <img class="wordmark" src="/images/wordmark.jpg" alt="Coast to Coast College Fair">

                <p class="script">We'll be right back</p>
                <h1>Down for maintenance</h1>
                <p class="lede">
                    We're making a few improvements to the site. Check back shortly —
                    the fair itself is right on schedule.
                </p>

                <a class="cta" href="mailto:{{ config('fair.coordinator.email') }}">Email us in the meantime</a>
            </div>
        </main>
    </div>
</body>
</html>
