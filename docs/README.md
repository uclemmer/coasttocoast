# Coast to Coast College Fair — Project Documentation

> **Build status (2026-08-19): all eight phases of [05-build-roadmap.md](05-build-roadmap.md) are
> implemented.** Pest is green and Pint is clean. What is left before launch is content and
> credentials rather than code — the owner content queue is at the bottom of
> [11-deployment.md](11-deployment.md), and one decision wanting the owner's eye is still flagged in
> [10-implementation-decisions.md](10-implementation-decisions.md) (D-5.4-a).
>
> **The design handoff's second delivery landed 2026-09-01** — the same bundle, extended with error
> pages, an admin-dashboard reference and an email template. The four error views, the restyled email
> layout and the two new `/staff` dashboard widgets are in
> [16-design-handoff-2026-09.md](16-design-handoff-2026-09.md), along with the four places the
> handoff asks for something this app deliberately does not do — starting with Filament.
>
> **Phase 8 rebuilt the public site** after the UI directive changed on 2026-08-19: public UI is
> Blade + Livewire + Flowbite, Filament is the admin backend only, and the old `SitePanelProvider`
> and its eight `Page` classes are gone. The visual design comes from the Claude Design handoff in
> [`docs/design-handoff/`](design-handoff/). Read the stack directive in
> [02-architecture.md](02-architecture.md) before touching any public page.

Planning documentation for rebuilding [coasttocoastcollegefair.com](https://www.coasttocoastcollegefair.com)
from scratch as a Laravel 13 application — Blade/Livewire/Flowbite on the public side, Filament v5 for
the admin backend — built on the owner's **`uclemmer/laravel-core`**
package (repo `github.com/uclemmer/laravel-core`, checked out in the workspace at
`projects/packages/core` — read its `/docs` too, including `docs/packages/` for the planned package
family). Written 2026-08-15/16; package path corrected 2026-08-16, and again 2026-08-18 when the
workspace became a submodule parent repo under `C:\Users\uriah\Code\Laravel`.

**Reading order** (a new Claude session or developer should read 00–04 and 07 before writing code, then work
cards from 05 under the rules in 06):

| Doc | Contents |
|---|---|
| [00-current-site-review.md](00-current-site-review.md) | What the existing site is, its site map, features to reproduce, and gaps to fix |
| [01-requirements.md](01-requirements.md) | Vision, confirmed decisions D1–D10 (payments, Filament, laravel-core, organizations, grants…), actors, functional & non-functional requirements, open questions |
| [02-architecture.md](02-architecture.md) | Stack (laravel-core, Blade/Livewire/Flowbite frontend, Filament v5 admin, Pest, Stripe, Postmark, Twilio), app layout, binding conventions, request flows, env config |
| [03-data-model.md](03-data-model.md) | Every table, relationship, enum, factory, and seeder, plus data lifecycle rules |
| [04-integrations.md](04-integrations.md) | Stripe / Postmark / Twilio / Filament integration design, failure handling, and test seams |
| [05-build-roadmap.md](05-build-roadmap.md) | Eight phases of task cards with dependencies and Definitions of Done — the work queue |
| [06-testing-strategy.md](06-testing-strategy.md) | Pest conventions, the critical test inventory, and reusable test patterns |
| [08-install-runbook.md](08-install-runbook.md) | The commands that bring the app up on Herd, what card 1.1 configured in `config/core.php`, and the one deviation from the card |
| [07-email-design.md](07-email-design.md) | Themed HTML email template, cross-year audience segmentation, and send tracking via the message log (laravel-core's EmailLog until 2026-08-30, `uclemmer/laravel-postmaster` since — see doc 15) |
| [09-package-wiring.md](09-package-wiring.md) | How this app consumes `uclemmer/laravel-core` (vcs + tagged release), publishing core's migrations, the permission sync the test suite needs, and why `canAccessPanel` returns `true` for the rep panel |
| [10-implementation-decisions.md](10-implementation-decisions.md) | Every judgement call an implementing session made without the owner present, with its reasoning and how to reverse it — **read this before questioning why something differs from 01–07** |
| [design-handoff/](design-handoff/) | The Claude Design handoff the public site was built from (2026-08-19, extended 2026-09-01) — the landing, interior, maintenance, error-page, admin-dashboard and email prototypes, and the token/type/spacing reference that declares colours, typography, spacing and copy final. Start at its [PROVENANCE.md](design-handoff/PROVENANCE.md) |
| [11-deployment.md](11-deployment.md) | Getting it live: host requirements, the deploy sequence, Stripe/Postmark/Twilio setup, backups, the go-live checklist, and the **owner content queue** — the things only Matt can supply |
| [12-ui-package-adoption.md](12-ui-package-adoption.md) | Adopting `uclemmer/laravel-ui` and retiring the rep panel: what that panel actually owns (all of auth), where each piece goes, and the published theme sheet repointed at the design handoff |
| [13-staff-admin.md](13-staff-admin.md) | Getting the fair's own admin off Filament and onto `/staff`: why not `/admin`, the pattern established on Sponsors, what Filament was doing for free, and the traps recorded for the six resources still to come |
| [14-core-04-upgrade.md](14-core-04-upgrade.md) | Upgrading to laravel-core 0.4 and off Filament for good: the eleven vestigial declarations that would have fatalled, the four tests ported, and the `@source` line whose compiled-CSS diff turned out to be a Blade comment |
| [15-core-05-and-postmaster.md](15-core-05-and-postmaster.md) | Core 0.5 removes its email log; the message log arrives from `uclemmer/laravel-postmaster`. The model alias, why `email_log_id` keeps its name, the two-pass data migration, the `core.admin.plugins` seam gaining its first entry, and the `v0.1.3` upgrade — where a version bump owed five unpublished migrations and failed at runtime in the Stripe tests rather than at install |
| [16-design-handoff-2026-09.md](16-design-handoff-2026-09.md) | The handoff's second delivery: the four error views over one shell, the email template restyled onto the brand green it had never been sent in, the admin dashboard's information design landed on `/staff` because the file it is drawn as no longer exists here, and the two widgets that were left unbuilt rather than filled with invented data |

**Golden rules** (duplicated from the docs because they matter):

1. **The UI stack is Tailwind + Alpine + Livewire, on `uclemmer/laravel-ui`** (owner directive
   2026-08-20, superseding the 2026-08-19 "Flowbite frontend, Filament backend" rule and the
   2026-08-16 "Filament only" rule before it). Filament and Flowbite are both being removed —
   frontend *and* admin. Reach for a `<x-ui::*>` component first; a missing one is a gap in the
   package, not a reason to open Filament.

   **Where the migration stands.** Flowbite is gone. The rep portal at `/portal` is rebuilt
   ([12-ui-package-adoption.md](12-ui-package-adoption.md)), and **this app's own Filament code is
   gone too** — all seven resources live at `/staff` as Livewire
   ([13-staff-admin.md](13-staff-admin.md)). What remains is not ours: `laravel-core` still
   requires Filament and still serves `/admin` for users, roles, content and
   settings. Both surfaces are live and agree on who is staff through the same `admin.access`
   permission. Read 12 and 13 before touching any UI, and
   [02-architecture.md](02-architecture.md) for the stack they supersede.
2. Build on **laravel-core** — never recreate a module it provides (admin shell, roles/permissions, contact, content blocks), nor one a satellite provides (the message log, from `uclemmer/laravel-postmaster` since 2026-08-30); package changes happen in their own repos (owner directive 2026-08-16).
3. Money is integer cents; price always comes from `Event::priceFor(organization)` (grant-aware, server-side); the Stripe webhook is the source of truth for payment state.
4. Every vendor SDK call lives behind a service interface (doc 04) so it can be faked in tests.
5. All email renders in the themed template and is logged via the message log (doc 07, doc 15).
6. Every task ships with Pest tests and a docs update in the same change (project instruction).
7. Check `01-requirements.md` → "Open questions" before making product assumptions; ask the owner (Matt).
