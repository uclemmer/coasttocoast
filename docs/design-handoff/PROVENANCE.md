# About this folder

Everything here except this file is the **Claude Design handoff** for the public site, supplied by
the owner on 2026-08-19 and **extended on 2026-09-01**. It is the visual source of truth for Phase 8
([../05-build-roadmap.md](../05-build-roadmap.md)); where the build departs from it, the departure is
recorded in [../10-implementation-decisions.md](../10-implementation-decisions.md) under D-8.x, and
the 2026-09-01 additions are worked through in [../16-design-handoff-2026-09.md](../16-design-handoff-2026-09.md).

## The second delivery (2026-09-01)

The owner uploaded the bundle again to `storage/app/private/design_handoff_college_fair_landing/`.
**It is the same handoff, extended** — `Landing Page.dc.html`, `Interior Page.dc.html`,
`Maintenance Page.dc.html`, `assets/`, `support.js` and `image-slot.js` are byte-identical to what
landed on 2026-08-19, and only the `README.md` changed, by addition. Diff before assuming a
redelivery is a redesign; three of the six deliverables here were already built.

What is new: `Error Pages.dc.html`, `Admin Dashboard.dc.html`, `email-template.html`, and
`screenshots/`.

| File | What it is |
|---|---|
| `README.md` | The handoff's own README — design tokens, type scale, spacing, component notes, and the asset list. It declares colours, typography, spacing and copy **final** |
| `Landing Page.dc.html` | The home page prototype |
| `Interior Page.dc.html` | The layout the other six public pages use |
| `Maintenance Page.dc.html` | Built as `resources/views/errors/503.blade.php` |
| `Error Pages.dc.html` | 404 / 403 / 500 / 503, one layout with a `code` tweak. Built as `resources/views/errors/{404,403,500}.blade.php` over `<x-errors.page>`; 503 keeps the maintenance design (doc 16) |
| `Admin Dashboard.dc.html` | Drawn as a **Filament** panel, which this app has not had since 2026-08-22. Read for its information design, not its implementation — what it contributed landed on `/staff` (doc 16) |
| `email-template.html` | The one deliverable the handoff marks usable as-is. `resources/views/emails/{layout,button,panel}.blade.php` are restyled onto it (doc 16) |
| `screenshots/` | A full-page PNG of each deliverable. The landing-page Google Maps iframe does not render in the captures |
| `assets/` | The three images the prototypes reference |
| `support.js`, `image-slot.js` | Claude Design's prototype runtime — the `<x-dc>` element the pages are built from. Open a `.dc.html` in a browser and it renders; delete these and it does not |

## Two things not to tidy up

**The files are unedited, including their Google Fonts `<link>` tags.** The build self-hosts those
three families instead (doc 10, D-8.1-a). Do not "fix" the handoff to match the build — it is a
record of what was designed, not a description of what was shipped.

**`screenshots/` are of the prototypes, not of this site.** They are stakeholder-review captures
taken from the `.dc.html` files, so they show the design's sample data — including a 2027 fair on a
date nobody has confirmed. Do not read a number off one and wire it to a page.

**`assets/` duplicates `public/images/` byte for byte, on purpose.** The prototypes are a frozen
record; `public/images/` is live and will change the moment the owner supplies the higher-resolution
cityscape and the transparent wordmark that doc 11's asset queue is waiting on. Pointing the
prototypes at `public/images/` would mean that, the day those land, the handoff quietly started
showing something other than the design.

## Why it lives here

It arrived in `storage/app/private/`, which is gitignored — so the docs referenced a path a fresh
clone would not have. Moved 2026-08-19.
