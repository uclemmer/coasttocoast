# 02 — Architecture

> **Purpose of this document:** How the application is put together — stack, packages, directory layout,
> and the conventions every future session must follow. The data model is in [03-data-model.md](03-data-model.md).

## Current repo state (verified 2026-08-15)

The repo at `C:\Users\uriah\Herd\coasttocoastcollegefair` is a **fresh Laravel skeleton**, served locally by
**Laravel Herd** on Windows:

- `laravel/framework ^13.8`, PHP `^8.3`
- **Pest 5** (`pestphp/pest`, `pest-plugin-laravel`) — the test framework; do not write PHPUnit-style classes
- `laravel/pint` (code style), `laravel/boost`, `laravel/pail`, `laravel/tinker`
- Tailwind CSS 4 + Vite 8 skeleton assets — **used**: they are the build chain for the public site (see the stack directive below)
- `.env` defaults: **SQLite** database, `database` queue/session/cache drivers, `log` mailer

No app code, packages, or migrations beyond the skeleton exist yet.

## Stack decisions

> **Owner directive (2026-08-19) — supersedes the 2026-08-16 "all UI is Filament" directive.**
> The two UI surfaces are built with different tools:
>
> - **Public site — Blade + Livewire + Flowbite.** Tailwind CSS 4 for styling, **Flowbite** as the
>   component library on top of it, Livewire full-page components where a page needs interactivity,
>   plain Blade views where it does not. No public-facing Filament panel.
> - **Admin (`/admin`) — Filament v5**, unchanged. laravel-core's prebuilt panel plus `FairPlugin`.
>
> **What this invalidates.** The public site was built the other way, under the old directive: it is
> currently `App\Providers\Filament\SitePanelProvider`, a Filament panel at the site root with eight
> `Page` classes under `app/Filament/Site/Pages/`. That was the stricter reading of the 2026-08-16
> directive, and it was flagged for the owner at the time — see
> [10-implementation-decisions.md](10-implementation-decisions.md) D-5.1-a. **This directive is the
> answer to that flag.** The panel now diverges from the documented direction and is queued for rework
> into Blade/Livewire/Flowbite route views; the page logic moves to controllers or Livewire components
> largely unaltered, which is what D-5.1-a predicted. No card has been scheduled for it yet.
>
> **Open — needs the owner's call: the rep portal (`/portal`).** It is a Filament panel today. Reps are
> external users rather than staff, and the sibling `duespay` project already decided that an
> external-user portal is "plain Livewire pages; owners get consumer UI, not admin software". If the
> same reading applies here, `RepPanelProvider` and the registration wizard move too — a much larger
> rework than the public site. Nothing has been changed on that assumption. Ask before acting.

| Concern | Choice | Notes |
|---|---|---|
| Framework | Laravel 13, **PHP 8.4** | Skeleton allows ^8.3, but `uclemmer/laravel-core` requires `^8.4` — set Herd/production to PHP 8.4 and bump the app's composer constraint |
| Foundation | **`uclemmer/laravel-core`** (owner's package, sibling repo at `C:\Users\uriah\Herd\laravel-core`) | Decision D6. Provides admin panel shell, roles/permissions, email logging, contact, content blocks, queue metrics, profiles, `core:doctor`. Install via a composer **path repository** in dev (`"repositories": [{"type": "path", "url": "../laravel-core"}]`); switch to VCS/Packagist for deployment. Read its `/docs` before building on it. |
| Database | SQLite in dev (as scaffolded); MySQL/Postgres in production | Keep migrations portable — no driver-specific SQL |
| Admin UI | **Filament v5** — settled: laravel-core pins `filament/filament ^5.0` | Backend only, per the directive above |
| Public UI | **Blade + Livewire + Flowbite** on Tailwind CSS 4 | Owner directive 2026-08-19. `flowbite` npm package, `@plugin 'flowbite/plugin'` in `resources/css/app.css`, `import 'flowbite'` in `resources/js/app.js` — `ckbs` is the reference wiring |
| Panels | **Admin** = laravel-core's prebuilt panel (`core.admin.enabled = true`, path `/admin`, branded via `core.admin.brand`), with the app's resources attached as a Filament plugin class registered in `core.admin.plugins` (the same seam `uclemmer/laravel-tickets` uses). **Rep** = app-owned `RepPanelProvider` (`/portal`). | App plugin: `App\Filament\FairPlugin` registering Events, Registrations, Payments, Organizations, Grants, Sponsors, FAQ, Messages resources + dashboard widgets |
| Public pages | **Blade route views + Livewire components, styled with Flowbite** on a shared public layout | Owner directive 2026-08-19. Still a Filament panel as built (`SitePanelProvider`) — diverges, queued for rework. Keep public UI thin; most complexity lives in the panels |
| Auth | Filament's built-in auth (login, registration, email verification, password reset) on the Rep panel. Admin access via **laravel-core roles/permissions** (`HasCoreRoles` trait on User; coordinator role holding app permissions; core's panel-access check). No `is_admin` boolean, no spatie/laravel-permission, no Fortify/Breeze/Jetstream. | Register app permissions through `core.permission_providers` so `core:sync-permissions` picks them up |
| Payments | `stripe/stripe-php` + Stripe Checkout (hosted) + webhooks | Never render card fields ourselves |
| Email | Postmark via Laravel's `postmark` mail transport (`symfony/postmark-mailer`); **all mail renders in the themed HTML template**; **all sends logged** via laravel-core's EmailLog (`core.email_log.enabled = true` — READ AT BOOT, restart workers after toggling) | Streams: `outbound` (transactional), `broadcast` (campaigns). Full email design: [07-email-design.md](07-email-design.md) |
| SMS | `twilio/sdk` behind our own `SmsService` + notification channel | Reminders + admin alerts only (decision D4) |
| PDF (printable check form) | `barryvdh/laravel-dompdf` | Mail-in registration form |
| Queues | `database` driver in dev; jobs small and idempotent | All mail/SMS sends are queued |

### How Filament maps to the product

- **Admin panel (`/admin`)** — laravel-core's prebuilt panel brings its own resources (email logs, contact
  submissions, content, roles/permissions, settings, queue tooling). Our `FairPlugin` adds Filament resources
  for Events, Registrations, Payments, Organizations (profile/logo, rep list, approve claims, retire reps,
  merge duplicates — R3.3a), Grants (review queue, approve-with-benefit, deny, revoke — R3.3b), Sponsors,
  FAQ items, and Messages (campaign composer, doc 07 §3); dashboard widgets (registrations count, revenue
  collected vs. pending checks, recent activity); custom actions ("Mark check received", "Refund", "Resend
  confirmation", CSV export via Filament's export action). Content blocks and the contact inbox come from
  laravel-core — we do not build those resources.
- **Rep portal (`/portal`)** — Filament panel with account signup (create or claim an organization — D9; pending
  claims wait for coordinator approval), a dashboard listing the organization's registrations and statuses,
  org profile editing (active reps), **grant application** per open event (D10), a **registration wizard**
  (Filament form wizard: confirm org details → rep contact → payment; grant-aware price shown) that hands off
  to Stripe Checkout, the check-instructions flow, or immediate confirmation when free, receipt downloads,
  profile/SMS opt-in management, and self-retire.
- **Public site** — Home, About, Representatives roster, Last Year, Sponsors, FAQ, Contact, and event pages.
  **Not Filament** (owner directive 2026-08-19): controllers or Livewire full-page components rendering Blade
  views on a shared public layout, styled with Tailwind 4 + **Flowbite** components and the fair's branding.
  The roster and event listings are the natural Livewire candidates; the rest are plain Blade.
  Page copy (Home/About blocks) comes from laravel-core's Content module (type `block`); the contact page uses
  its contact component/routes (`core.contact.*` config: recipients, receipt, honeypot, throttle).
  **As built this is still a Filament panel** — see the divergence note in the stack directive above.

### laravel-core integration checklist (first build session)

1. Path repository + `composer require uclemmer/laravel-core` (PHP 8.4).
2. Run its install command: publish `config/core.php` + migration stubs; migrate.
3. Enable/configure: `auth` (roles), `admin` (prebuilt panel, brand "Coast to Coast College Fair",
   `plugins => [App\Filament\FairPlugin::class]`), `email_log` (enabled, `store_body`, prune ≥ 400 days),
   `content`, `contact` (recipients = coordinator), `queue`, `profile` as needed — each module is opt-in.
4. `User` model: add `HasCoreRoles` (+ profile/settings traits if used). Seed a `coordinator` role; register
   app permissions via `core.permission_providers`; run `core:sync-permissions`.
5. Schedule `core:prune-email-logs` (+ our pruning) in `routes/console.php`; wire `core:doctor` into CI/deploy.
6. Record the resolved laravel-core + Filament versions in this file.

**Status (2026-08-18):** all six steps are done. `config/core.php` was published **by hand** (a copy of the
package's file with the fair's values) — do not `vendor:publish` it again. The migrations are published and
run; the commands, and the table of what was changed, are in [08-install-runbook.md](08-install-runbook.md).
Step 1's path repository was replaced by a tagged VCS release on 2026-08-16 — see
[09-package-wiring.md](09-package-wiring.md) for why and how to upgrade.

**Resolved versions** (read from `vendor/composer/installed.json` on 2026-08-18 — this is card 1.1's
remaining deliverable):

| Package | Constraint | Resolved version |
|---|---|---|
| `uclemmer/laravel-core` | `^0.2` (vcs `github.com/uclemmer/laravel-core.git`) | **v0.2.0** |
| `filament/filament` | `^5.0` (transitive, via laravel-core) | **v5.7.6** |
| `laravel/framework` | `^13.8` | **v13.24.0** |
| `livewire/livewire` | `^4.3` (direct; Filament v5 requires `^4.1`) | **v4.4.0** |
| `pestphp/pest` | `^5.0` | **v5.0.4** |
| PHP | `^8.4` | **8.4.24** (Herd) |

**Panels as built:**

| Panel | Id | Path | Owner | Access |
|---|---|---|---|---|
| Admin | `core` | `/admin` | laravel-core `CorePanelProvider` | `admin.access` permission (coordinator role, or super admin) |
| Rep portal | `rep` | `/portal` | `App\\Providers\\Filament\\RepPanelProvider` | authenticated + verified email; membership gating lands with card 3.0 |
| Public site | `site` | `/` | `App\\Providers\\Filament\\SitePanelProvider` | none. **Diverges from the 2026-08-19 UI directive** — should be Blade/Livewire/Flowbite route views, not a panel |

`User::canAccessPanel()` switches on the panel id rather than using core's `CanAccessCorePanel` trait — see
the deviation note in [05-build-roadmap.md](05-build-roadmap.md) card 1.1.

## Application layout

Standard Laravel conventions plus:

```
app/
  Enums/                    MembershipStatus, RegistrationStatus, PaymentMethod, PaymentStatus,
                            GrantStatus, GrantBenefit, MessageChannel, Audience, DeliveryStatus
  Models/
  Policies/                 used by Filament resources for authorization
  Filament/
    Admin/                  Resources/, Pages/, Widgets/ for the Admin panel
    Rep/                    Resources/, Pages/ (RegistrationWizard) for the Rep portal
  Http/
    Controllers/            Public pages + Stripe webhook only — thin
    Requests/               Form Requests for non-Filament endpoints (interest form, webhook glue)
  Services/
    Payments/StripeCheckoutService.php
    Payments/CheckPaymentService.php
    Sms/SmsService.php      (interface + TwilioSms + NullSms fake)
    RegistrationService.php
    GrantService.php        apply/approve/deny/revoke; Event::priceFor() delegates here (doc 03)
    RosterService.php
    AudienceBuilder.php     cross-year campaign audiences (doc 07 §2)
  Filament/FairPlugin.php   registers app resources onto laravel-core's panel
  Listeners/                LinkEmailLogToRecipient (on core's EmailLogged event, doc 07 §4)
  Jobs/                     SendEventBroadcast, SendEventReminders
  Notifications/            One class per row of the R4 comms matrix — all render via the themed layout
resources/views/emails/     themed layout + components (doc 07 §1); overrides core::emails.layout
routes/
  web.php                   public routes (panels register their own routes)
  webhooks.php              POST /webhooks/stripe (csrf-exempt)
docs/                       ← this documentation (project instruction: keep updated)
tests/
  Feature/                  mirrors app structure (Filament resources tested via Livewire test helpers)
  Unit/
```

**Conventions (binding for future sessions):**

1. Filament resources/pages stay thin; business rules live in `Services`. Filament actions call services.
2. All external I/O (Stripe, Postmark, Twilio) goes through a service with an interface so tests can fake it. Never call the Stripe or Twilio SDKs directly from a Filament class or controller.
3. Money is integer cents everywhere (`price_cents`, `amount_cents`).
4. Statuses are PHP backed enums in `app/Enums`, stored as strings (Filament renders enums natively via `HasLabel`/`HasColor`).
5. Every migration ships with model factory updates; every feature ships with Pest tests (see 06).
6. Authorization goes through Policies backed by laravel-core permissions (`HasCoreRoles::can(...)` via Gate); admin-panel access is core's panel-access check, rep-panel access requires a verified email.
7. Run `vendor/bin/pint` and `php artisan test` before finishing any task.
8. Update the relevant `/docs` file in the same commit as the code it describes.

## Request flows

### Stripe registration (happy path)

```
Rep (logged into /portal, ACTIVE member) → Registration wizard (Filament form wizard)
  → RegistrationService::create()            price_cents = Event::priceFor(org) (grant-aware)
      price 0 (free grant) → status=confirmed immediately, no payment; receipt + admin alert; DONE
      else                 → status=pending_payment, method=stripe
  → StripeCheckoutService::createSession()   amount = registration price_cents snapshot; metadata: registration_id
  → redirect to Stripe-hosted Checkout
Stripe → POST /webhooks/stripe (checkout.session.completed)
  → verify signature → idempotency check (stripe_webhook_events)
  → RegistrationService::confirmPayment()    status=confirmed; payment row created
  → queued: receipt email (Postmark), admin alert email+SMS
Rep returns to /portal (success state read from DB; webhook is the source of truth)
```

### Check registration

```
Rep → wizard → method=check
  → registration: status=pending_payment
  → queued: instructions email + PDF form (dompdf); admin alert
… check arrives by mail …
Admin → Registration resource → "Mark check received" action (amount, check #, date)
  → status=confirmed → queued receipt email
```

### Reminder broadcast

```
Admin → Message resource → compose: audience (cross-year, doc 07 §2) + channels + schedule
  → Message row persisted → SendEventBroadcast job (delayed)
  → AudienceBuilder resolves recipients AT SEND TIME → message_recipients rows frozen
  → per-recipient queued notifications: themed Postmark email; Twilio SMS only where sms_opt_in
  → core EmailLog captures each send; EmailLogged listener links log → recipient row (doc 07 §4)
```

## Environments & config

Add to `.env.example` (real values only in `.env`, never committed):

```
STRIPE_KEY=            # publishable
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
POSTMARK_TOKEN=
MAIL_MAILER=postmark   # 'log' in local dev
MAIL_FROM_ADDRESS=contact@coasttocoastcollegefair.com
TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM=           # SMS-capable number
ADMIN_ALERT_EMAIL=
ADMIN_ALERT_PHONE=
```

Local dev uses Stripe test keys + Stripe CLI (`stripe listen --forward-to coasttocoastcollegefair.test/webhooks/stripe`),
`MAIL_MAILER=log`, and the `NullSms` fake unless explicitly testing Twilio.

## Deployment notes (to firm up later)

Production target is undecided (likely Forge/Ploi-style VPS or Laravel Cloud). Requirements whatever the host:
HTTPS, a queue worker, `php artisan schedule:run` cron (scheduled broadcasts), MySQL/Postgres, Postmark domain
verification (DKIM/return-path), Stripe webhook endpoint registered, Twilio number provisioned, and
`php artisan filament:optimize` in the deploy script.
