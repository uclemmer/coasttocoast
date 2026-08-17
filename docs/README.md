# Coast to Coast College Fair — Project Documentation

Planning documentation for rebuilding [coasttocoastcollegefair.com](https://www.coasttocoastcollegefair.com)
from scratch as a Laravel 13 + Filament v5 application built on the owner's **`uclemmer/laravel-core`**
package (sibling repo `C:\Users\uriah\Herd\laravel-core` — read its `/docs` too). Written 2026-08-15/16.

**Reading order** (a new Claude session or developer should read 00–04 and 07 before writing code, then work
cards from 05 under the rules in 06):

| Doc | Contents |
|---|---|
| [00-current-site-review.md](00-current-site-review.md) | What the existing site is, its site map, features to reproduce, and gaps to fix |
| [01-requirements.md](01-requirements.md) | Vision, confirmed decisions D1–D10 (payments, Filament, laravel-core, organizations, grants…), actors, functional & non-functional requirements, open questions |
| [02-architecture.md](02-architecture.md) | Stack (laravel-core, Filament v5, Pest, Stripe, Postmark, Twilio), app layout, binding conventions, request flows, env config |
| [03-data-model.md](03-data-model.md) | Every table, relationship, enum, factory, and seeder, plus data lifecycle rules |
| [04-integrations.md](04-integrations.md) | Stripe / Postmark / Twilio / Filament integration design, failure handling, and test seams |
| [05-build-roadmap.md](05-build-roadmap.md) | Seven phases of task cards with dependencies and Definitions of Done — the work queue |
| [06-testing-strategy.md](06-testing-strategy.md) | Pest conventions, the critical test inventory, and reusable test patterns |
| [08-install-runbook.md](08-install-runbook.md) | The commands that bring the app up on Herd, what card 1.1 configured in `config/core.php`, and the one deviation from the card |
| [07-email-design.md](07-email-design.md) | Themed HTML email template, cross-year audience segmentation, and send tracking via laravel-core's EmailLog |

**Golden rules** (duplicated from the docs because they matter):

1. UI is **Filament only** — no hand-built Blade/Tailwind/Livewire/Flowbite UI (owner directive 2026-08-16).
2. Build on **laravel-core** — never recreate a module it provides (admin shell, roles/permissions, email log, contact, content blocks); package changes happen in its own repo (owner directive 2026-08-16).
3. Money is integer cents; price always comes from `Event::priceFor(organization)` (grant-aware, server-side); the Stripe webhook is the source of truth for payment state.
4. Every vendor SDK call lives behind a service interface (doc 04) so it can be faked in tests.
5. All email renders in the themed template and is logged via laravel-core's EmailLog (doc 07).
6. Every task ships with Pest tests and a docs update in the same change (project instruction).
7. Check `01-requirements.md` → "Open questions" before making product assumptions; ask the owner (Matt).
