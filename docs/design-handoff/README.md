# Handoff: Coast to Coast College Fair — 2026 Landing Page Facelift

## Overview
A visual refresh of coasttocoastcollegefair.com. The fair is an annual one-evening event in
Chattanooga, TN where 100+ college admissions representatives meet local sophomores, juniors,
and parents. This redesign is aimed primarily at **college representatives** — the primary
conversion is rep registration.

Deliverables in this bundle:
1. **Landing page** — full marketing page with hero, countdown, registration detail, venue, sponsors, contact.
2. **Interior page (kitchen sink)** — the secondary page layout plus a complete component inventory
   (typography, buttons, badges, alerts, cards, tabs, table, pagination, accordion, form elements).
3. **Maintenance page** — the page Laravel serves when the app is in maintenance mode.
4. **Error pages** — one template covering 404, 403, 500, and 503 (copy + action buttons vary per code).
5. **Admin dashboard** — reference design for the Filament panel (nav, widgets, tables, theme).
6. **Email template** — send-ready themed HTML email boilerplate.

## About the Design Files
The `.dc.html` files in this bundle are **design references created in HTML** — prototypes that
show intended look, copy, and behavior. They are **not production code to copy directly**. They use
a lightweight in-browser component runtime (`support.js`) and all styling is written as **inline
styles** for previewing purposes.

The task is to **recreate these designs in the target codebase**:

- **Laravel + Blade** for templating
- **TailwindCSS** for styling — translate the inline styles below into Tailwind utilities and extend
  `tailwind.config.js` with the tokens in the Design Tokens section
- **Livewire** for the interactive pieces (countdown, tabs, accordion, contact form, registration form)
- **Filament** for the admin side (managing registrations, sponsors, event date, FAQ content)

Suggested Blade structure:

```
resources/views/
  layouts/
    app.blade.php            # <html>, head, fonts, @yield
  components/
    site-header.blade.php    # nav (shared by all pages)
    site-footer.blade.php    # footer (shared by all pages)
    button.blade.php         # variant: primary | secondary | ghost | danger
    badge.blade.php          # variant: registered | pending | due | cancelled
    alert.blade.php          # variant: success | warning | danger
    card.blade.php
    field.blade.php          # label + input + error slot
  pages/
    landing.blade.php
    interior.blade.php       # the kitchen-sink / secondary layout
  errors/
    503.blade.php            # maintenance page (Laravel serves this in maintenance mode)
    404.blade.php            # all four error views extend one error layout —
    403.blade.php            #   see "Error Pages" below; only code, copy, and
    500.blade.php            #   action buttons differ per view
  emails/
    layout.blade.php         # from email-template.html; slots for preheader/headline/body/CTA
livewire/
  EventCountdown.php
  TabPanel.php
  FaqAccordion.php
  ContactForm.php
  RegistrationForm.php
```

## Fidelity
**High-fidelity.** Colors, typography, spacing, and copy are final. Recreate pixel-for-pixel using
Tailwind utilities. Exact values are documented below and are also readable in the HTML files.

---

## Design Tokens

### Colors
| Token | Hex | Usage |
|---|---|---|
| `green-600` (brand) | `#188042` | Primary brand green — buttons, links, accents, panels. Taken from the existing site's wordmark. |
| `green-700` | `#0f5c2e` | Primary button hover |
| `green-650` | `#146a37` | Green text on white/light buttons |
| `green-50` | `#eef7f1` | Ghost/secondary button hover, inline code bg |
| `green-100` | `#f2f8f4` | Section tint, page-header bg, table header |
| `green-200` | `#bfe0cb` | Light borders on green tints |
| `green-300` | `#bfe6cd` | Caveat script text on green panels |
| `green-light` | `#dcefe2` | Body copy on green panels |
| `green-hero` | `#b8f0ca` | Script accent over hero photo |
| `ink-900` | `#1a2b21` | Headings, footer background |
| `ink-800` | `#22302a` | Default body text |
| `ink-700` | `#3a4e42` | Long-form body copy |
| `ink-600` | `#44584c` | Secondary copy, labels |
| `ink-500` | `#5a6e62` | Card copy, table cells |
| `ink-400` | `#7a8f81` | Uppercase eyebrow labels, inactive tabs |
| `ink-300` | `#93a89a` | Disabled button text |
| `footer-text` | `#c4d4c9` | Footer text on ink-900 |
| `border` | `#e3ece6` | Default 1px borders, nav/footer dividers |
| `border-soft` | `#eef2ef` | Table row / accordion dividers |
| `input-border` | `#cfe0d5` | Form input border (1.5px) |
| `input-bg` | `#fbfdfb` | Form input background |
| `placeholder` | `#9ab0a2` | Input placeholder text |
| `disabled-bg` | `#dfe8e2` | Disabled button background |
| `danger` | `#b23b2e` | Danger button, error borders |
| `danger-dark` | `#8f2d23` | Danger hover, error text |
| `danger-50` | `#f6e9e7` / `#fdf7f6` | Danger alert bg / error input bg |
| `danger-200` | `#e5c6c1` | Danger alert border |
| `warn` | `#c8891f` | Warning alert left border |
| `warn-dark` | `#8a5a12` | Warning text |
| `warn-50` | `#fdf3e6` | Warning alert / badge bg |
| `warn-200` | `#eed9b5` | Warning alert border |

### Typography
Google Fonts (single request):
```
https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Caveat:wght@600;700&family=Source+Sans+3:ital,wght@0,400;0,600;1,400&display=swap
```

| Role | Family | Weight | Notes |
|---|---|---|---|
| Display / headings | Montserrat | 800 | `text-transform: uppercase`, `letter-spacing: -0.01em`, `line-height: 1.1–1.2` |
| Sub-headings | Montserrat | 700 | 17–24px, uppercase for section titles only |
| Buttons / labels / badges | Montserrat | 700 | uppercase, `letter-spacing: 0.04em` (buttons), `0.08–0.12em` (eyebrows/badges) |
| Script accent | Caveat | 700 | 24–40px, brand green, hero instance rotated `-2deg` |
| Body | Source Sans 3 | 400 / 600 | 15–18px, `line-height: 1.6–1.75` |
| Inline code | ui-monospace / Menlo | 400 | 14.5px |

Type scale actually used (px): 11.5, 12.5, 13, 13.5, 14, 14.5, 15, 15.5, 16, 16.5, 17, 18, 22, 24, 26, 28, 30, 34, 36, 44, 48, 68.
Fluid headings use `clamp()` — e.g. hero H1 `clamp(40px, 5.4vw, 68px)`, section H2 `clamp(26px, 3vw, 36px)`.

### Spacing
Base scale (px): 4, 6, 8, 10, 12, 14, 16, 18, 20, 24, 26, 28, 32, 36, 40, 44, 48, 56, 64, 72, 80.
- Content container: `max-width: 1140px`, `padding: 0 24px`, centered
- Full-bleed sections inside the container use `margin: 0 calc(50% - 50vw)` (set `overflow-x: clip` on body)
- Nav horizontal padding: `max(24px, calc((100% - 1140px)/2))`
- Vertical section rhythm: 56–80px

### Radius
`6px` buttons · `8px` inputs, alerts, small tiles · `10px` tables, bordered panels · `12px` cards ·
`14px` green registration panel · `999px` badges/pills.

### Shadows
- Card: `0 1px 3px rgba(26,43,33,0.06)`
- Map embed: `0 2px 16px rgba(26,43,33,0.12)`
- Buttons over hero photo: `0 4px 18px rgba(0,0,0,0.35)`
- Wordmark on maintenance page: `0 6px 28px rgba(8,24,14,0.45)`
- Hero text (no dark overlay — see Hero note): `text-shadow: 0 2px 4px rgba(8,24,14,0.85), 0 6px 28px rgba(8,24,14,0.65)`

---

## Screens / Views

### 1. Landing Page — `Landing Page.dc.html`
**Purpose:** convert college representatives to register; orient them on venue, parking, and sponsors.

**Header / nav** (shared component)
- `display:flex; align-items:center; gap:28px`; `padding:14px` + container gutter; `border-bottom:1px solid #e3ece6`; white bg
- Left: wordmark image, `height:48px; width:auto`, links to home
- Then, in order: **About** (`#about`), **Representatives** (`#register`), **Sponsors** (`#sponsors`), **FAQ** (`#faq`), **Contact** (`#contact`) — 14.5px, weight 600, `#22302a`, hover `#188042`
- Right group (`margin-left:auto`, `gap:14px`): **Log in** as a plain link (same styling as nav links), then **Register** as a solid button (`#188042` bg, white text, Montserrat 700 13.5px uppercase, `padding:12px 22px`, `radius:6px`, hover `#0f5c2e`)
- ⚠️ `#about` and `#faq` have **no matching sections yet** — wire to real routes or build those sections.

**Hero** — full-bleed, no dark overlay
- `position:relative`, edge-to-edge via `margin: 0 calc(50% - 50vw)`, `overflow:hidden`, `min-height: min(78vh, 640px)`
- Content centered with `display:grid; align-content:center; justify-items:center; text-align:center`, `padding: clamp(48px,7vw,88px) clamp(20px,5vw,64px)`
- Background: `assets/cityscape.jpg` absolutely positioned, `object-fit:cover`, `object-position:center 40%`, `filter:saturate(1.15)`
- **Deliberately has NO dark overlay** — the client wants the painted cityscape colors to stay vivid. Legibility comes from layered `text-shadow` (values above) on every text element, not a scrim. Preserve this.
- Inner column `max-width:820px`
- Eyebrow: Caveat 700 `clamp(26px,3vw,36px)`, `#b8f0ca`, `rotate(-2deg)` — content: formatted event date + `· 6:30–8:00 p.m. · Chattanooga Convention & Trade Center`
- H1: "Bring your college to Chattanooga" — Montserrat 800 `clamp(40px,5.4vw,68px)`, uppercase, white, `max-width:20ch`
- Body: 18px/1.65, white, `max-width:62ch` — "Each spring, more than one hundred colleges and universities meet the sophomores, juniors, and parents of Tennessee's tri-state area in a single evening. Registration includes your exhibit table, the pre-fair dinner reception, complimentary parking, and student volunteers to carry your materials in."
- CTAs (`flex; gap:14px; justify-content:center; margin-top:32px`): **Register your college** (solid green) and **Venue & parking** (`rgba(255,255,255,0.94)` bg, `#146a37` text, `2px solid #ffffff` border, hover `#ffffff`)

**Countdown** (Livewire, `wire:poll.1s` or a JS interval)
- `padding:64px 0` (equal spacing above/below — centers it between hero and green panel), centered
- Heading: Caveat 700 30px `#188042` — "The fair opens in…"
- Four units in `flex; justify-content:center; gap:clamp(20px,5vw,64px)`: Days / Hours / Minutes / Seconds
- Numbers: Montserrat 800 `clamp(40px,4.4vw,60px)`, tabular figures; **Days is green `#188042`**, the rest `#1a2b21`. Hours/Minutes/Seconds zero-padded to 2 digits.
- Labels: 12.5px, weight 600, uppercase, `letter-spacing:0.12em`, `#7a8f81`
- **Past-date behavior:** when the event date has passed, the number grid **hides entirely** and the heading becomes "This year's fair has concluded — details for next spring are coming". Registration copy also swaps (see below).

**Registration panel** (`#register`)
- Green card: `background:#188042`, `radius:14px`, `padding:clamp(36px,5vw,64px)`, white text, `margin-bottom:80px`
- Two columns: `minmax(0,6fr) minmax(0,5fr)`, `gap:48px clamp(32px,6vw,90px)`, `align-items:start`
- Left: Caveat eyebrow "For college representatives" (`#bfe6cd`) → H2 "What registration includes" (Montserrat 800 `clamp(26px,3vw,36px)` uppercase) → 16px/1.7 body (`#dcefe2`) → deadline line (`#bfe6cd`, 15px) → white **Begin registration** button (`#188042` text, hover `#eef7f1`)
- Deadline copy is date-driven: open → "Registration for the 2027 fair is open now and closes Tuesday, April 13, 2027."; past → "Registration for this year's fair has closed. Join the mailing list to be notified when next spring's registration opens."
- Right: 4 stacked items, `padding:16px 0`, `border-bottom:1px solid rgba(255,255,255,0.25)` (none on last). Each = Montserrat 700 17px title + 14.5px/1.6 `#dcefe2` body:
  1. **Exhibit table on the fair floor** — "6:30–8:00 p.m., alongside 100+ peer institutions."
  2. **Pre-fair dinner reception** — "5:00–6:00 p.m. in downtown Chattanooga, with local high school counselors."
  3. **Complimentary parking** — "In the Convention Center garage, reserved for representatives."
  4. **Volunteer drop-off service** — "Student volunteers meet you on Carter Street and carry your exhibit materials to your table."

**Venue & parking** (`#venue`)
- Two columns `minmax(0,5fr) minmax(0,6fr)`, `gap:40px clamp(32px,6vw,90px)`, `align-items:center`, `padding-bottom:80px`
- Left: Caveat eyebrow "Venue & parking" → H2 "Chattanooga Convention & Trade Center" (Montserrat 800 `clamp(26px,3vw,34px)` uppercase `#1a2b21`) → 3 labeled blocks (`gap:16px`), each a 12.5px uppercase `#7a8f81` label + 16px/1.6 value:
  - **Address** — "1 Carter Plaza, Chattanooga, TN 37402"
  - **Representative drop-off** — "Pull up to the College Rep Drop-Off Area on Carter Street; student volunteers will take your exhibit materials and direct you to the garage."
  - **Parking** — "Complimentary for college representatives in the Convention Center parking garage."
- Right: Google Maps iframe, `aspect-ratio:4/3`, `radius:12px`, map shadow, `loading="lazy"`. Replace the placeholder embed with a pin on 1 Carter Plaza.

**Sponsors** (`#sponsors`) — full-bleed green band
- `margin: 0 calc(50% - 50vw) 80px`, `background:#188042`, `padding:48px` + container gutter
- Caveat 700 28px `#bfe6cd` centered: "Sponsored by"
- Logo row: `flex; justify-content:center; align-items:center; gap:clamp(28px,5vw,72px); flex-wrap:wrap; margin-top:28px`
- Each: white logo tile `150×80`, `radius:8px`, `object-fit:contain` + caption below (Montserrat 600 12.5px uppercase `letter-spacing:0.08em` `#dcefe2`)
- Four sponsors: **Baylor School**, **Girls Preparatory School**, **McCallie School**, **St. Andrew's-Sewanee** — logo files are **not yet supplied**; the prototype uses drop placeholders. Model these as a Filament-managed `sponsors` resource (name, logo upload, url, sort order).

**Contact** (`#contact`)
- `grid-template-columns: repeat(auto-fit, minmax(300px,1fr))`, `gap:48px clamp(32px,6vw,100px)`, `padding-bottom:80px`
- Left: Caveat eyebrow "Contact" → H2 "Write to the fair" → 16px/1.7 intro → mailing address block:
  Coast to Coast College Fair / ATTN: Meg Conner / 171 Baylor School Road / Chattanooga, TN 37405
- Right: Livewire form — Name, Email, Institution, Message (textarea, `min-height:110px`, vertical resize) + solid green **Send message** button (`justify-self:start`)
- Inputs: 15.5px, `padding:12px 14px`, `1.5px solid #cfe0d5`, `radius:8px`, `#fbfdfb` bg; **focus** → `border-color:#188042; outline:none`
- Labels: 13px, weight 600, uppercase, `letter-spacing:0.06em`, `#44584c`, stacked above input with `gap:6px`

**Footer** (shared component)
- `background:#1a2b21`, `color:#c4d4c9`, `min-height:72px`, `padding: 0` + container gutter,
  `display:flex; align-items:center; justify-content:space-between` (text vertically centered), 13.5px
- Left: "© 2007–2026 Coast to Coast College Fair · Chattanooga, Tennessee" — Right: "Powered by Uriah Clemmer"

### 2. Interior Page (kitchen sink) — `Interior Page.dc.html`
**Purpose:** the secondary page layout + the full component inventory to build the Blade/Tailwind component library from.

- Same nav and footer as the landing page
- **Page header:** `background:#f2f8f4`, `border-bottom:1px solid #e3ece6`, `padding:36px` + gutter. Contains a breadcrumb (13.5px, `#7a8f81`, `/` separators, current crumb `#44584c` weight 600), Caveat eyebrow, H1 (Montserrat 800 `clamp(30px,3.6vw,44px)` uppercase), and a 17px/1.65 intro (`max-width:62ch`)
- **Body grid:** `minmax(0,1fr) 260px`, `gap:56px clamp(32px,5vw,72px)`, `align-items:start`; main `padding:56px 0` with `gap:56px` between sections
- **Sidebar:** `position:sticky; top:24px` — "On this page" nav card (`1px solid #e3ece6`, `radius:10px`, `padding:16px 18px`) + a green-tint help card
- **Section headers:** Montserrat 800 24px uppercase `#1a2b21` + a `52×3px` `#188042` rule below, `margin-bottom:20px`

Components documented in the file:
- **Typography** — H3, body, inline link/bold/italic/code, blockquote (`border-left:4px solid #188042`, `#f2f8f4` bg, `padding:16px 20px`), `ul`, `ol`
- **Buttons** — primary, secondary (2px green border, so `padding:12px 24px` vs primary's `14px 26px` to keep equal height), ghost, danger, disabled (`#dfe8e2`/`#93a89a`, `cursor:not-allowed`)
- **Badges** — pill, Montserrat 700 11.5px uppercase `letter-spacing:0.1em`, `padding:5px 12px`: Registered (solid green), Pending (green tint + border), Invoice due (amber), Cancelled (red)
- **Alerts** — `radius:8px`, `1px` tinted border + `4px` colored left border, `padding:14px 18px`, Montserrat 700 14px title + 15px/1.6 body. Success / warning / danger.
- **Cards** — 3 variants: image card (image area is a fixed `168px` tall block, not aspect-ratio — the wrapper is `overflow:hidden`, so an intrinsic-ratio child clips the body), stat card (Montserrat 800 34px green figure), solid-green CTA card
- **Tabs** — Livewire. Tab strip `border-bottom:2px solid #e3ece6`; buttons `padding:12px 18px`, Montserrat 700 14px uppercase; inactive `#7a8f81`, active `#188042` + `3px` green bottom border with `margin-bottom:-2px` to sit on the strip. Panel copy: Schedule / Fees / Shipping.
- **Table** — wrapper `1px solid #e3ece6`, `radius:10px`, `overflow:hidden`; `border-collapse:collapse`. Header row `#f2f8f4`, Montserrat 700 12.5px uppercase `letter-spacing:0.08em` `#44584c`, `padding:12px 16px`. Body rows `border-top:1px solid #eef2ef`; first cell weight 600 `#1a2b21`, others `#5a6e62`; status cell holds a badge. Columns: Institution / State / Table / Status.
- **Pagination** — `flex; gap:6px`; items `radius:6px`, `1px solid #e3ece6`, 14px; current page solid green white text; hover `#f2f8f4`. Previous / 1 / 2 / 3 / Next.
- **Accordion** — Livewire, single-open. Wrapper bordered `radius:10px`; each row `border-bottom:1px solid #eef2ef` except last. Trigger is a full-width flex button (`padding:16px 18px`, Montserrat 700 16px, hover `#f8fbf9`) with a green `+`/`–` glyph at 20px on the right. Open panel: `padding:0 18px 18px`, 16px/1.7 `#3a4e42`. Clicking the open row closes it.
- **Form elements** — text, email, select, textarea, **error state** (`1.5px solid #b23b2e` border, `#fdf7f6` bg, `#b23b2e` label, 13.5px `#8f2d23` message below), checkboxes and radios (`17×17`, `accent-color:#188042`), fieldset + legend (`1px solid #e3ece6`, `radius:8px`, `padding:14px 16px`; legend 13px uppercase `#44584c` with `padding:0 6px`), submit + save-draft button pair. Max form width 560px; two-up rows use `grid-template-columns:1fr 1fr; gap:16px`.

### 3. Maintenance Page — `Maintenance Page.dc.html`
**Purpose:** Laravel's `php artisan down` view → `resources/views/errors/503.blade.php`.
- Single full-screen panel: `min-height:100vh`, `display:grid`, content centered, `padding:clamp(48px,8vw,96px) clamp(20px,5vw,64px)`, `overflow:hidden`
- Same cityscape background treatment as the hero (cover, `center 40%`, `saturate(1.15)`, **no overlay**), same layered text shadows
- Centered stack: wordmark (`min(360px,80vw)` wide, `radius:8px`, shadow) → Caveat "We'll be right back" (`#b8f0ca`, `rotate(-2deg)`, `margin-top:36px`) → H1 "Down for maintenance" (Montserrat 800 `clamp(30px,4.2vw,48px)` uppercase white) → 18px/1.65 body (`max-width:52ch`) → white outline **Email us in the meantime** button (`mailto:`)
- **No nav and no footer** — intentional; the site is down, so there is nothing to navigate to.

### 4. Error Pages — `Error Pages.dc.html`
**Purpose:** Laravel error views → `resources/views/errors/{404,403,500,503}.blade.php`. One layout, per-code content — the design file has a `code` tweak to preview each.
- Same full-screen treatment as the maintenance page (cityscape background, no overlay, no nav/footer), with the wordmark at `min(300px,70vw)`.
- Centered stack: wordmark → giant status code (Montserrat 800 `clamp(96px,16vw,170px)`, white, layered text shadows) → Caveat script line (`#b8f0ca`, `rotate(-2deg)`) → uppercase H1 (Montserrat 800 `clamp(26px,3.6vw,42px)`) → 18px/1.65 body (`max-width:52ch`) → button row (`flex`, `gap:14px`, wrap, centered).
- **Buttons:** every code gets white **Back to home** (link to `route('home')`). 403 adds a ghost **Log in** (`rgba(8,24,14,0.35)` bg, white 2px border); 500 and 503 add a ghost **Contact us** (`mailto:`).
- **Per-code copy:**

| Code | Script line | H1 | Body |
|---|---|---|---|
| 404 | Well, this is awkward | Page not found | The page you're looking for has moved or never existed. Check the address, or head back to the fair. |
| 403 | Members only | Access denied | You don't have permission to view this page. Log in with a representative account, or head back home. |
| 500 | That's on us | Something went wrong | An unexpected error occurred on our end. We're on it — try again in a moment, or let us know what happened. |
| 503 | Hang tight | Service unavailable | The site is temporarily busy or being updated. Try again in a few minutes — the fair itself is right on schedule. |

Note: Laravel serves the maintenance page (artisan down) from `503.blade.php`; if you want the maintenance design for downtime and this 503 design for genuine overload errors, render the maintenance view from a custom `down` template instead.

### 5. Admin Dashboard — `Admin Dashboard.dc.html`
**Purpose:** reference for theming and laying out the **Filament** admin panel. Do not hand-build this —
implement it as Filament resources/widgets and apply the brand theme; the design shows the target look.

Layout: `grid-template-columns: 248px 1fr`, `min-height:100vh`, page bg `#f2f5f3`.

- **Sidebar** — `#14261d` bg, `#e6efe9` text. Brand block (Montserrat 800 14px uppercase white + 12px `#7fa88f` "Admin"), then nav links: 14.5px, `padding:9px 12px`, `radius:6px`; active = solid `#188042` white weight 600; inactive `#bcd0c3`, hover `rgba(255,255,255,0.06)` + white. Items: Dashboard, Registrations, Representatives, Sponsors, FAQs, Pages, Emails; bottom group (above a `rgba(255,255,255,0.08)` divider): Event settings, Users & roles. → Filament navigation groups.
- **Topbar** — white, `height:60px`, `border-bottom:1px solid #dde6df`: page title (Montserrat 700 16px), search input (280px, `#f8faf8` bg, focus border `#188042`), "View site ↗" link, avatar circle (`#188042`, initials) + name.
- **Stat cards** — `repeat(auto-fit,minmax(200px,1fr))`, white, `1px solid #dde6df`, `radius:8px`, `padding:18px 20px`: 12.5px uppercase `#5a6a61` label, Montserrat 800 30px value, 13px delta (positive `#146a37`, attention `#8a6116`). → Filament StatsOverview widget.
- **Bar chart** — registrations/week, 150px tall flex columns, `gap:8px`; bars `radius:4px 4px 0 0`, past weeks `#9ecbaf`, current `#188042`; 10.5px `#8a978f` week labels. → Filament ChartWidget.
- **Recent registrations table** — white bordered card, header row `#f8faf8` 12px uppercase, rows `border-top:1px solid #eef2ef`, hover `#f8faf8`. Status pills (11–12px, 700, `radius:99px`, `padding:3px 10px`): Confirmed `#e2f3e8`/`#146a37`, Pending `#fdf3e2`/`#8a6116`, Waitlist `#eef2ef`/`#5a6a61`. → Registrations resource table.
- **Right rail (360px)** — solid-green countdown card (Montserrat 800 34px "N days", white-on-green settings button), tasks card (checkboxes `accent-color:#188042`), activity feed (8px green dot + 13.5px line + 12px `#8a978f` timestamp).
- All data shown is **sample data**.

### 6. Email Template — `email-template.html`
**Purpose:** boilerplate for all fair emails (confirmations, reminders, announcements). This one IS
usable as-is — table-based, inline styles, 600px, tested patterns for Gmail/Outlook/Apple Mail.
- Structure: green `#188042` header bar (swap the text block for a hosted wordmark `<img>`), white body card (eyebrow / headline / body / CTA button), divider, "At a glance" info rows, gray footer with unsubscribe + preferences links.
- Email-safe fonts only: Arial (headings/buttons) + Georgia (body) — web fonts are unreliable in email.
- Per-send fill-ins: hidden preheader `<span>`, headline, body paragraphs, CTA label + href, info rows.
- Hook into Laravel as a Blade mailable layout (`resources/views/emails/layout.blade.php`) with slots for the fill-ins.

---

## Interactions & Behavior

| Behavior | Detail |
|---|---|
| Nav links | Same-page anchors on the landing page; convert to named routes in Blade. `html { scroll-behavior: smooth }`. |
| Nav / link hover | Text links `#22302a` → `#188042`. Underline offset `3px` on body links. |
| Button hover | primary `#188042`→`#0f5c2e`; secondary/ghost transparent-or-white→`#eef7f1`; white-on-green→`#eef7f1`; danger `#b23b2e`→`#8f2d23`; over-photo white button `0.94` alpha→`#ffffff`. |
| Input focus | `border-color:#188042`, `outline:none`. |
| Countdown | Ticks every second. Days unpadded; H/M/S zero-padded. On/after the event datetime, hide the grid and swap the heading + registration deadline copy. Drive from one configurable event datetime (Filament setting) — prototype default `2027-04-20T18:30:00-04:00`. |
| Tabs | Click sets active index; only the active panel renders. Default index 0. |
| Accordion | Single-open; clicking the open row collapses it (allows all-closed). Default: first row open. |
| Forms | Not wired in the prototype. Add server-side validation: required name/email/institution/message; email format; show the documented error state inline. |
| Responsive | Everything uses `clamp()`, `auto-fit` grids, and `flex-wrap`, so it degrades reasonably, but **mobile was not explicitly designed** — the nav in particular needs a hamburger/drawer at small widths, and the two-column grids should collapse to one. Confirm mobile behavior before shipping. |
| Full-bleed technique | `margin: 0 calc(50% - 50vw)` inside the 1140px container; requires `overflow-x: clip` on `body` to avoid a horizontal scrollbar. In Tailwind, prefer moving these sections outside the container instead. |

## State Management
Minimal — no client-side store needed.

| State | Where | Notes |
|---|---|---|
| `eventDate` | Server/config, Filament-editable | Drives hero eyebrow, countdown, and registration deadline copy. |
| Countdown remaining | Livewire component or small JS interval | Derived from `eventDate`; 1s tick. |
| Active tab index | Livewire (`public int $tab = 0`) | |
| Open accordion index | Livewire (`public ?int $open = 0`) | `null`/`-1` = all closed. |
| Form fields + errors | Livewire form objects | Standard Laravel validation. |
| Sponsors, FAQ, registrations | Database + Filament resources | Sponsors: name, logo, url, sort. FAQ: question, answer, sort. Registrations: institution, rep name, email, rep count, notes, payment method, status (registered/pending/waitlist/cancelled), table assignment. |

## Assets
In `assets/` in this bundle — all provided by the client from the existing site:
- `wordmark.jpg` — green "Coast to Coast College Fair" wordmark. Used in the nav at 48px tall and on the maintenance page. **Request a transparent PNG or SVG** — the JPG has a white box that limits placement.
- `cityscape.jpg` — painted/aerial Chattanooga riverfront illustration. Hero and maintenance backgrounds. **Request the highest-resolution original** for full-bleed hero use.
- `logo-2023.jpg` — alternate logo mark, not currently placed.
- **Missing:** the four sponsor school logos, and any photography of the fair floor (the interior-page card image is a placeholder).
- Google Maps embed on the landing page is a placeholder pointing at Chattanooga generally — repoint to 1 Carter Plaza.

## Files
| File | What it is |
|---|---|
| `Landing Page.dc.html` | Landing page design reference |
| `Interior Page.dc.html` | Secondary page layout + kitchen-sink component inventory |
| `Maintenance Page.dc.html` | Laravel maintenance (503) page |
| `Error Pages.dc.html` | 404 / 403 / 500 / 503 error views (one layout, `code` tweak) |
| `Admin Dashboard.dc.html` | Admin dashboard reference for the Filament panel — dark-green sidebar nav (Dashboard/Registrations/Representatives/Sponsors/FAQs/Pages/Emails + Event settings, Users & roles), topbar with search + user, 4 stat cards, registrations-per-week bar chart, recent-registrations table with status badges (Confirmed `#e2f3e8`/`#146a37`, Pending `#fdf3e2`/`#8a6116`, Waitlist `#eef2ef`/`#5a6a61`), countdown panel, tasks, activity feed. Map to Filament: sidebar = navigation groups, stat cards = widgets (StatsOverview), chart = ChartWidget, table = the Registrations resource table. Theme Filament with the token palette (primary `#188042`, sidebar `#14261d`). All data shown is sample data. |
| `email-template.html` | Send-ready themed email boilerplate (600px, table-based, inline styles — works in Gmail/Outlook/Apple Mail). Swap the header text block for a hosted wordmark image, fill in preheader/headline/body/CTA per send. |
| `support.js` | Prototype-only runtime for the design files. **Do not port.** |
| `image-slot.js` | Prototype-only image placeholder component. **Do not port** — replace with real `<img>` tags. |
| `assets/` | Client-supplied brand images |
| `screenshots/` | Full-page PNG of each deliverable for stakeholder review (landing, interior, maintenance, error page — 404 shown, admin dashboard, email). Note: the landing-page Google Maps iframe doesn't render in captures. |

## Open Questions for the Client
1. Sponsor logo files (4 schools) — needed as transparent PNG/SVG.
2. Transparent wordmark + high-res cityscape original.
3. `#about` and `#faq` nav links have no destination yet — separate pages, or new landing-page sections?
4. Confirmed 2027 event date, registration deadline, and fee schedule (the kitchen-sink fee copy is placeholder).
5. What does **Log in** lead to — a rep portal? If so, that's a scope item beyond these three pages.
6. Mobile nav pattern (hamburger drawer assumed).
