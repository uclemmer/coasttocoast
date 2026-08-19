# About this folder

Everything here except this file is the **Claude Design handoff** for the public site, supplied by
the owner on 2026-08-19. It is the visual source of truth for Phase 8
([../05-build-roadmap.md](../05-build-roadmap.md)); where the build departs from it, the departure is
recorded in [../10-implementation-decisions.md](../10-implementation-decisions.md) under D-8.x.

| File | What it is |
|---|---|
| `README.md` | The handoff's own README — design tokens, type scale, spacing, component notes, and the asset list. It declares colours, typography, spacing and copy **final** |
| `Landing Page.dc.html` | The home page prototype |
| `Interior Page.dc.html` | The layout the other six public pages use |
| `Maintenance Page.dc.html` | Built as `resources/views/errors/503.blade.php` |
| `assets/` | The three images the prototypes reference |
| `support.js`, `image-slot.js` | Claude Design's prototype runtime — the `<x-dc>` element the pages are built from. Open a `.dc.html` in a browser and it renders; delete these and it does not |

## Two things not to tidy up

**The files are unedited, including their Google Fonts `<link>` tags.** The build self-hosts those
three families instead (doc 10, D-8.1-a). Do not "fix" the handoff to match the build — it is a
record of what was designed, not a description of what was shipped.

**`assets/` duplicates `public/images/` byte for byte, on purpose.** The prototypes are a frozen
record; `public/images/` is live and will change the moment the owner supplies the higher-resolution
cityscape and the transparent wordmark that doc 11's asset queue is waiting on. Pointing the
prototypes at `public/images/` would mean that, the day those land, the handoff quietly started
showing something other than the design.

## Why it lives here

It arrived in `storage/app/private/`, which is gitignored — so the docs referenced a path a fresh
clone would not have. Moved 2026-08-19.
