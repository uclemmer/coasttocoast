# Coast to Coast College Fair — Project Documentation

> **Build status (2026-08-19): all seven phases of [05-build-roadmap.md](05-build-roadmap.md) are
> implemented.** 609 Pest tests pass, Pint is clean. What is left before launch is content and
> credentials rather than code — the owner content queue is at the bottom of
> [11-deployment.md](11-deployment.md), and one decision wanting the owner's eye is still flagged in
> [10-implementation-decisions.md](10-implementation-decisions.md) (D-5.4-a).
>
> **But the UI directive changed on 2026-08-19** and the public site no longer matches it. Public UI
> is now Blade + Livewire + Flowbite; Filament is the admin backend only. The public site is built as
> a Filament panel (`SitePanelProvider`, eight `Page` classes) and is **queued for rework**. This is
> the owner's answer to the D-5.1-a flag. Read the stack directive in
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
| [05-build-roadmap.md](05-build-roadmap.md) | Seven phases of task cards with dependencies and Definitions of Done — the work queue |
| [06-testing-strategy.md](06-testing-strategy.md) | Pest conventions, the critical test inventory, and reusable test patterns |
| [08-install-runbook.md](08-install-runbook.md) | The commands that bring the app up on Herd, what card 1.1 configured in `config/core.php`, and the one deviation from the card |
| [07-email-design.md](07-email-design.md) | Themed HTML email template, cross-year audience segmentation, and send tracking via laravel-core's EmailLog |
| [09-package-wiring.md](09-package-wiring.md) | How this app consumes `uclemmer/laravel-core` (vcs + tagged release), publishing core's migrations, the permission sync the test suite needs, and why `canAccessPanel` returns `true` for the rep panel |
| [10-implementation-decisions.md](10-implementation-decisions.md) | Every judgement call an implementing session made without the owner present, with its reasoning and how to reverse it — **read this before questioning why something differs from 01–07** |
| [11-deployment.md](11-deployment.md) | Getting it live: host requirements, the deploy sequence, Stripe/Postmark/Twilio setup, backups, the go-live checklist, and the **owner content queue** — the things only Matt can supply |

**Golden rules** (duplicated from the docs because they matter):

1. **Frontend is Blade + Livewire + Flowbite; backend is Filament** (owner directive 2026-08-19,
   superseding the 2026-08-16 "Filament only" rule). Public pages are Blade route views and Livewire
   components styled with Tailwind 4 + Flowbite. `/admin` stays Filament. Do not build a public-facing
   Filament panel. The existing `SitePanelProvider` predates this and is queued for rework — see
   [02-architecture.md](02-architecture.md).
2. Build on **laravel-core** — never recreate a module it provides (admin shell, roles/permissions, email log, contact, content blocks); package changes happen in its own repo (owner directive 2026-08-16).
3. Money is integer cents; price always comes from `Event::priceFor(organization)` (grant-aware, server-side); the Stripe webhook is the source of truth for payment state.
4. Every vendor SDK call lives behind a service interface (doc 04) so it can be faked in tests.
5. All email renders in the themed template and is logged via laravel-core's EmailLog (doc 07).
6. Every task ships with Pest tests and a docs update in the same change (project instruction).
7. Check `01-requirements.md` → "Open questions" before making product assumptions; ask the owner (Matt).
