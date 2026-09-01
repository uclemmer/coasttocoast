# 16 — The second design handoff (2026-09-01)

The owner uploaded a Claude Design bundle to `storage/app/private/design_handoff_college_fair_landing/`.
This doc records what was in it, what was built from it, and — more usefully — the four places where
the handoff asks for something this application deliberately does not do.

The bundle now lives in [`design-handoff/`](design-handoff/), and the storage copy was deleted once
it was committed and pushed. That is the move [its PROVENANCE](design-handoff/PROVENANCE.md)
describes, and the reason for it: `storage/app/private/` is gitignored, so a doc that pointed at it
would name a path a fresh clone does not have. **Every `storage/app/private/…` path below is
therefore historical** — it says where the delivery arrived, not where to find it now.

---

## 1. It is the same handoff, extended

Diff first. `Landing Page.dc.html`, `Interior Page.dc.html`, `Maintenance Page.dc.html`, `assets/`,
`support.js` and `image-slot.js` are **byte-identical** to the 2026-08-19 delivery. Only the
`README.md` changed, and only by addition.

That matters because three of the bundle's six deliverables were already built, in Phase 8, and a
redelivery read as a redesign would have meant rebuilding the landing page, the interior layout and
the maintenance page against files that had not moved.

**Do this on the next upload, before reading a word of the new README.** `docs/design-handoff/` is
the previous delivery, so it is the thing to diff against; point `NEW` at wherever the bundle
arrived and let `diff -rq` name the changed files for you.

```bash
NEW="storage/app/private/<the-new-bundle>"; diff -rq docs/design-handoff "$NEW" | grep -v PROVENANCE
```

Then read the changed ones in full — a delivery can rewrite a prototype as easily as add one, and
`diff -rq` tells you *which* files moved, not *whether* the design did:

```bash
diff -u docs/design-handoff/README.md "$NEW/README.md"
```

`PROVENANCE.md` is ours rather than the designer's, so it is filtered out; every other `Only in
docs/design-handoff` line is a real question — a file the new bundle dropped.

**New in this delivery:** `Error Pages.dc.html`, `Admin Dashboard.dc.html`, `email-template.html`,
and `screenshots/`.

---

## 2. Error pages — built

`resources/views/errors/{404,403,500}.blade.php`, over a new shared shell at
`resources/views/components/errors/page.blade.php`. Copy is the handoff's, which marks it final.

**The maintenance page was folded into the same shell.** The handoff draws the two as one picture,
and they differ only in the wordmark's width, the heading's step, and the presence of the giant
numeral. Passing no `code` selects the maintenance proportions; that is the whole of the branch.

**The shell touches no assets, no routes and no database, and each of those is load-bearing:**

- `php artisan down --render=errors::503` renders the view **once** and serves the resulting HTML
  flat out of `storage/framework/down` for the whole outage — which is exactly the window in which
  `public/build` is being replaced. An `@vite` call bakes that moment's asset hash into the file and
  the page 404s its own stylesheet. This was already true of the old 503 and is why it was written
  standalone.
- `500.blade.php` renders **while something is already broken**. If what broke was the asset build,
  the stylesheet, or a provider, an error page that depends on any of those fails to render its own
  failure. That is also why the buttons use `url('/')` rather than `route('site.home')`: a
  named-route lookup that throws inside the error view turns a handled 500 into a blank page.

**Buttons, per the design:** home on all four; log in on 403; the public contact address
(`fair.contact.email`) on 500. The maintenance page keeps its existing `fair.coordinator.email`
mailto, untouched.

**The cost of that, and it is a real one: the error pages render in the system font stack.** The
handoff marks typography final, and these four pages do not get Montserrat, Caveat or Source Sans 3
— `@fonts` reads the build manifest, which is the file being swapped during the window 503 exists
for. The stacks degrade sensibly (`Montserrat, 'Segoe UI', system-ui`, `Caveat, 'Segoe Script',
cursive`) and this was already the maintenance page's bargain; what changed is that it now applies
to 404s, which a visitor hits far more often than an outage. Reversing it for 404 and 403 alone is
possible — guard `@fonts` on `file_exists(public_path('build/manifest.json'))`, as the app layout
already guards `@vite` — and would mean two shells or a conditional inside one. Not worth it for a
page the visitor is leaving; recorded so the next person does not have to re-derive it.

**The giant numeral is `aria-hidden`.** It is decoration; the sentence a screen reader needs is the
H1 under it, and announcing "404" first is noise.

### 503 stays the maintenance page

The handoff draws **two** 503s — "Down for maintenance" in `Maintenance Page.dc.html` and "Service
unavailable" in `Error Pages.dc.html` — and names the conflict itself: Laravel serves
`errors/503.blade.php` for both `artisan down` and a genuine 503.

This app keeps the maintenance design there. Three reasons, in order of weight: it is what
[doc 11's](11-deployment.md) deploy runbook prerenders and what `FrontendWiringTest` pins; the
handoff's own default says so; and the alternative it offers — a custom `down` template so the error
design can own 503 — buys a distinction nobody outside the building can act on differently. Both
sentences tell a visitor to come back shortly.

### What the tests pin

`tests/Feature/Foundation/ErrorPagesTest.php`. Two things, and not much else is worth pinning:

1. **The shell does not reach for the build.** A stray `@vite` would not fail a page-renders test.
   It would fail in production, once, during an outage.
2. **The framework actually picks the views up.** `errors/404.blade.php` is wired to the exception
   handler by filename alone — nothing imports it, nothing references it, and a typo in the name is
   a silent fall-back to Laravel's stock grey page.

The 500 test sets `config(['app.debug' => false])` first, **because that is the only way that view
renders at all.** With debug on, the framework serves its own stack trace and `500.blade.php` never
runs; a broken one would sit undiscovered until the day it was needed.

---

## 3. Email template — built

`email-template.html` is the one deliverable the handoff marks usable as-is, and the app already had
a working layout at `resources/views/emails/layout.blade.php` (doc 07). The layout, the button and
the panel were restyled onto the design; the slots did not change, so the seven notification views
needed almost nothing.

What changed:

- **Green, not blue.** `fair.brand.color_primary` had sat on Laravel's stock `#1d4ed8` since card
  6.0, and **every email this app has ever sent went out in it.** The default is now the handoff's
  `#188042`, in `config/fair.php` and `.env.example`. It survived four months because the one
  surface that reads the value had no test that looked at a colour — the config is also the last
  consumer standing, since Filament's theme left on 2026-08-22 and the web pages take their palette
  from the `@theme` block in `resources/css/app.css`, which an inbox cannot see.
- **Arial and Georgia.** The site self-hosts Montserrat, Caveat and Source Sans 3; the handoff
  specifies email-safe stacks instead, because a web font in an inbox is unreliable at best and
  stripped at worst. A Google Fonts `<link>` here would also leak the recipient to a third party on
  open, which is the same objection [doc 10's](10-implementation-decisions.md) D-8.1-a answers for
  the public pages. This is the one surface where the brand typography does not follow; the colours
  still do.
- **An eyebrow and a headline.** The design's body card is eyebrow → headline → copy. `$title` now
  renders as the visible headline as well as the `<title>`, so all seven notifications gained the
  shape without a per-view edit; `admin-alert.blade.php` lost the bold first paragraph that had been
  standing in for one. The eyebrow is the fair's date and venue, passed by the three views that have
  an event in hand.
- **"At a glance".** `x-emails::panel` is a rule and a heading now rather than a bordered grey box.
  In an inbox the message is already a card, and a second card inside it reads as a quotation from
  somewhere else.

Two incidental fixes, both in `admin-alert.blade.php`: its `<x-emails::button>` was closed with
`</x-emails.components.button>`, a name that has not existed since the components were flattened.

### The unsubscribe links are deliberately absent

The handoff's footer carries **Unsubscribe** and **Email preferences**, pointing at `/unsubscribe`
and `/preferences`. Neither route exists. One-click unsubscribe is `uclemmer/laravel-postmaster`'s
subscription feature, which has not landed (see [doc 15](15-core-05-and-postmaster.md)).

Wiring them now would ship two dead links to every recipient of a campaign, which is worse than not
offering them — a broken unsubscribe link is a spam complaint. A test asserts they are absent; its
job is to keep the omission deliberate rather than forgotten, and it should be **deleted** the day
the routes exist.

This is the general shape of the trap the workspace notes call out: **design comps are drawn with
plausible-looking data.** This one's info rows advertise a 2027 fair on a date nobody has confirmed,
at "Free for students and families", and its footer links two routes that were never built. Read the
values on a comp before wiring one to a page.

---

## 4. Admin dashboard — the design intent, on `/staff`

`Admin Dashboard.dc.html` is drawn as a **Filament** panel, and its README says so four times:
"reference design for the Filament panel", "implement it as Filament resources/widgets", "sidebar =
navigation groups", "stat cards = widgets (StatsOverview)".

**This application has had no Filament since 2026-08-22**, and the workspace directive of 2026-08-20
forbids adding it back — see [doc 13](13-staff-admin.md) and [doc 14](14-core-04-upgrade.md). The
handoff is a month older than that removal and cannot know it.

So what was taken from that file is its **information design** — what a coordinator should see
first, and next to what — rather than its implementation. The existing `/staff` overview already had
the design's stat cards and its recent-registrations table with status badges. Two pieces were
missing and are now built, on `uclemmer/laravel-ui`:

- **Registrations per week**, twelve weeks, via `<x-ui::chart.bars>`. Grouped **in PHP, not in
  SQL**: a `GROUP BY` on a week number needs a date function, and the ones that exist differ by
  driver — this app runs SQLite in tests and MySQL in production, and `WEEK()` does not exist in the
  first while `strftime('%W')` does not exist in the second. Twelve weeks of one fair's
  registrations is a few hundred rows at the outside. The buckets are built first and then filled,
  so a week nobody registered in is a zero-height bar rather than a missing one — which is the whole
  point of plotting it.
- **The countdown card**, in a 360px rail. Whole days from midnight, not the public page's ticking
  clock: the coordinator is reading a deadline, not watching one, and diffing two timestamps would
  make a fair opening at 18:30 tomorrow "one day away" all morning and "zero days away" all
  afternoon.

### Two things from the design were not built, on purpose

- **Tasks and the activity feed.** Neither has a table behind it, and both are drawn with sample
  data. Building the widget first and inventing the data to fill it is how a dashboard ends up
  lying. If the owner wants either, they are features with a data model, not a layout change.
- **The chart's current-week highlight.** The design colours the current week's bar differently from
  the eleven behind it. `x-ui::chart.bars` colours a **series**, not a bar. Reaching for the package
  component first is the workspace rule, so the highlight is dropped rather than hand-rolling a
  second chart; per-bar colour is a gap to raise in `uclemmer/laravel-ui` if anyone misses it. The
  current week is already the one on the right.

### The sidebar and topbar were not restyled

The design's chrome is a dark `#14261d` sidebar with a white topbar. `/staff` runs the shell in
[doc 13](13-staff-admin.md), which is deliberately the same shape as the rep portal's — "two shells
that behave differently for no reason is worse than a little duplication". Restyling one of the two
would undo that, and restyling both is a decision about the portal that this handoff does not cover.
Raise it with the owner before touching either.

---

## 5. Still open

Everything in the handoff's own "Open Questions for the Client" is still open, and two of them now
block visible things:

| # | Question | What it blocks |
|---|---|---|
| 1 | Sponsor logo files (4 schools), transparent PNG/SVG | The sponsors band still renders name-only tiles |
| 2 | Transparent wordmark, high-res cityscape | The wordmark's white box on the error pages and the maintenance page — visible in `screenshots/error-page.png` |
| 4 | Confirmed 2027 date, deadline, fee schedule | The email eyebrow reads whatever the active fair says; the comp's own values are placeholders |
| 6 | Mobile nav pattern | Unchanged from Phase 8 |

Added by this delivery: **one-click unsubscribe and an email-preferences page**, which are
postmaster features rather than design work — see §3.
