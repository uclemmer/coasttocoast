# 05 — Build Roadmap & Task Cards

> **Purpose of this document:** The phased plan, broken into task cards a future Claude session can pick up
> and complete independently. **Work the phases in order** — each card lists its dependencies.
>
> **Instructions for implementing sessions (read this box before starting any card):**
> 1. Read docs 00–04, 06 and 07 first; treat decisions D1–D10 (doc 01) as settled. When working on anything
>    touching a laravel-core module, also read that module's doc in `C:\Users\uriah\Herd\laravel-core\docs\`.
> 2. Take ONE card at a time. Mark it in the checklist below when done (edit this file).
> 3. Every card's Definition of Done includes: Pest tests written and passing (`php artisan test`),
>    `vendor/bin/pint` clean, factories updated, and the relevant docs updated in the same change.
> 4. UI is Filament only (owner directive 2026-08-16) — no hand-built Blade/Tailwind/Livewire/Flowbite UI.
> 5. If a card conflicts with reality (package versions, Filament API changes), fix forward and record the
>    deviation in this file under the card.

## Phase checklist

- [ ] Phase 1 — Foundation (cards 1.1–1.4) — **1.1 done 2026-08-16; 1.2 done 2026-08-18**
- [ ] Phase 2 — Domain & admin core (cards 2.1–2.6)
- [ ] Phase 3 — Rep portal & registration (cards 3.0–3.5)
- [ ] Phase 4 — Payments (cards 4.1–4.3)
- [ ] Phase 5 — Public site (cards 5.1–5.4)
- [ ] Phase 6 — Communications (cards 6.0–6.6)
- [ ] Phase 7 — Launch hardening (cards 7.1–7.3)

---

## Phase 1 — Foundation

### Card 1.1 — Install laravel-core + panels
**Depends on:** nothing. **Prereq:** switch the environment to PHP 8.4 and bump the app's composer `php` constraint.
**Do:** Follow the "laravel-core integration checklist" in doc 02: path repository, `composer require uclemmer/laravel-core` (brings Filament v5), publish config + migration stubs, migrate. Enable core's prebuilt Admin panel (`/admin`, branded) with an empty `App\Filament\FairPlugin` registered in `core.admin.plugins`. Create app-owned `RepPanelProvider` (`/portal`: registration, login, email verification, password reset). Users: add `phone`, `sms_opt_in` (NO `is_admin` — roles come from core); `HasCoreRoles` on User; seed `coordinator` role; register app permissions via `core.permission_providers`; run `core:sync-permissions`. Factory states `coordinator()`, `smsOptedIn()`.
**Tests:** guests redirected from both panels; user without coordinator role blocked from `/admin`; coordinator reaches `/admin`; verified rep reaches `/portal`; unverified rep blocked; `core:doctor` passes.
**Record the pinned laravel-core + Filament versions in doc 02.**

**Status: done (2026-08-16).** Shipped: `composer.json` (PHP ^8.4, path repository `../laravel-core`,
`uclemmer/laravel-core: "@dev"`), `config/core.php` (published copy, edited — deltas table in
[08-install-runbook.md](08-install-runbook.md)), `app/Filament/FairPlugin.php` (discovery-based, empty),
`app/Providers/Filament/RepPanelProvider.php` (`/portal`, id `rep`, login + registration + password reset +
email verification), `app/Support/Permissions.php` (8 app permissions, registered via
`core.permission_providers`), `User` (`HasCoreRoles`, `FilamentUser`, `MustVerifyEmail`, `phone`,
`sms_opt_in`, `smsReachable()` scope), the users migration, factory states `coordinator()` /
`smsOptedIn()`, `RoleSeeder`, scheduled core pruning in `routes/console.php`, env keys, and Pest coverage in
`tests/Feature/Panels/` + `tests/Feature/Foundation/`.

**Deviation:** laravel-core's `CanAccessCorePanel` trait is deliberately NOT applied to `User` — it answers
for every panel and this app has two, so it would lock reps out of `/portal`. `User::canAccessPanel()` keeps
the identical `admin.access` Gate check for the `core` panel and requires a verified email for `rep`.

**Still owed by this card:** the resolved laravel-core + Filament versions, which can only be read after
`composer update` runs on the Windows host — record them in [02-architecture.md](02-architecture.md).


### Card 1.2 — Enums, base models, migrations, factories
**Depends on:** 1.1.
**Do:** Create everything in doc 03: enums (incl. `MembershipStatus`, `GrantStatus`, `GrantBenefit`); models + migrations + factories for organizations, events, registrations, grants, payments, stripe_webhook_events, event_interests, sponsors, sponsor_staff, faq_items, messages, message_recipients; users membership columns (organization_id, membership_status, retired_at/by). (No contact_submissions / content_blocks — laravel-core provides those.) Relationships + casts. Event helpers `isRegistrationOpen()`, `isFull()`, `previousPublished()` scope, and **`priceFor(Organization)`** (grant-aware pricing — doc 03). User scopes `activeReps()`/`pendingReps()`.
**Tests:** unit tests per model — relationships resolve, casts work, `isRegistrationOpen()` truth table (before open, open, after close, null window, unpublished), `isFull()` with/without capacity, `priceFor()` truth table (no grant / free / custom price / percent-off rounding / denied-revoked-pending grants ignored).

**Status: done (2026-08-18).** Shipped: nine enums in `app/Enums` (the six planned plus
`MessageChannel`, `Audience` and `DeliveryStatus` — see doc 03 and decision D-1.2-a); twelve
migrations `2026_08_18_0001xx`–`0012xx`; models `Organization`, `Event`, `Registration`, `Grant`,
`Payment`, `StripeWebhookEvent`, `EventInterest`, `Sponsor`, `SponsorStaff`, `FaqItem`, `Message`,
`MessageRecipient`; membership columns, relationships and `activeReps()` / `pendingReps()` /
`actsForOrganization()` on `User`; a factory for every model with the states the later cards need;
`Event::priceFor()` / `isRegistrationOpen()` / `isFull()` / `previousPublished()` / `active()`.
112 unit tests in `tests/Unit/Models/` (144 suite-wide), Pint clean.

**Deviations, all recorded with reasoning in [10-implementation-decisions.md](10-implementation-decisions.md):**
capacity counts awaiting-payment registrations as well as confirmed (D-1.2-b); `priceFor()` charges
list price for an approved grant with no benefit recorded (D-1.2-c); percentage discounts round down
(D-1.2-d); the duplicate and one-grant-per-event rules stay at service level rather than becoming
partial unique indexes, which SQLite/MySQL/Postgres do not express portably (D-1.2-e);
`message_recipients.email_log_id` carries no foreign key so `core:prune-email-logs` cannot take
campaign history with it (D-1.2-g); `tests/Pest.php` now gives the Unit suite an application and a
database (D-1.2-h).

### Card 1.3 — Seeders
**Depends on:** 1.2.
**Do:** Dev seeder per doc 03 (2025 + 2026 past events with rosters incl. lapsed reps — audiences need two past years, 2027 placeholder event flagged TODO-OWNER, sponsors/staff, FAQ, core content blocks from doc 00 copy, coordinator user). Production seeder (content + coordinator only).
**Tests:** seeder runs green; seeded counts/keys assertions.

### Card 1.4 — Service scaffolding & config
**Depends on:** 1.2.
**Do:** `SmsService` interface + `TwilioSms` + `NullSms`; `PaymentGateway` interface + empty `StripeCheckoutService`; `RegistrationService`, `RosterService` shells; container bindings (env-dependent); add all env keys from doc 02 to `.env.example`; `config/services.php` entries; install `stripe/stripe-php`, `twilio/sdk`, `barryvdh/laravel-dompdf`.
**Tests:** container resolves interfaces to correct implementations per env config; `NullSms` records/logs.

## Phase 2 — Domain & admin core

### Card 2.1 — Event resource (admin)
**Depends on:** 1.2. Filament resource: CRUD per R3.2, slug auto-generation, money input (dollars ↔ cents), registration window pickers, publish toggle. Policy: admin only.
**Tests:** create/edit via Livewire resource tests; validation (closes_at after opens_at, price ≥ 0); policy enforcement.

### Card 2.2 — Organization & Registration resources (admin)
**Depends on:** 2.1. Organizations (R3.3a): CRUD incl. profile fields + logo upload, rep list with membership statuses, approve/deny pending claims, retire reps, **merge-duplicates action** (repoints users/registrations/grants). Registrations: list with filters (event, status, method), search, detail view showing grant/price snapshot, `show_on_roster` toggle, notes, cancel action, resend-confirmation action (queues notification), CSV export. Manual-entry creation (user_id null).
**Tests:** filters/search return expected rows; claim approve/deny transitions + notifications queued; retire; merge repoints all relations; cancel sets status+timestamp; export contains expected columns; policies.

### Card 2.3 — RegistrationService
**Depends on:** 1.4, 2.1. `create()` (duplicate rules R2.7: hard-block same organization+event non-cancelled; acting rep must be an ACTIVE member; warning flag on normalized-name match at org creation), price snapshot via `Event::priceFor()`, **free-grant path confirms immediately with no payment**, `confirmPayment()`, `cancel()`, capacity enforcement.
**Tests:** unit — duplicate block, pending/retired rep rejected, capacity full rejection, free-grant immediate confirm (no payment row, receipt queued), discounted price snapshotted, confirm transitions + confirmed_at, cancel rules, event-closed rejection.

### Card 2.6 — GrantService + Grants resource (admin)
**Depends on:** 1.2, 2.1. `GrantService`: `apply()` (active rep, one non-withdrawn application per org+event, only while event registration is open or upcoming), `approve()` (coordinator picks benefit: free / custom price / percent off), `deny()` (reason required), `revoke()` (only while unused). Filament Grants resource (R3.3b): review queue filtered by event/status, approve/deny/revoke actions, usage indicator. Decision notifications (R4).
**Tests:** application rules; approve sets benefit + queues rep email; deny requires reason; revoke blocked once used; permissions.

### Card 2.4 — Sponsors & FAQ resources (admin)
**Depends on:** 1.2. CRUD + `sort_order` reordering. (Content blocks and the contact inbox are laravel-core resources — configure/verify, don't build.)
**Tests:** resource CRUD, reorder persists; core content + contact resources reachable by coordinator.

### Card 2.5 — Admin dashboard widgets
**Depends on:** 2.2. Widgets: confirmed count vs. capacity, revenue collected vs. pending-check amounts, recent registrations. All for the "active" (next upcoming published) event.
**Tests:** widget queries against seeded fixtures return correct numbers.

## Phase 3 — Rep portal & registration

### Card 3.0 — Signup with org create/claim + membership lifecycle
**Depends on:** 1.1, 1.2. Signup flow (D9): create a new organization (profile form incl. admissions contact fields; membership active; normalized-name dedupe warning; admin alert) or claim an existing one from a searchable directory (membership pending; admin alert; "awaiting approval" portal state). Self-retire action (confirmation modal). Claim approved/denied notifications.
**Tests:** both signup paths; pending rep blocked from registering/grants/org-edit but can browse; approval flips to active + email; denial email; self-retire revokes org rights; retired rep login still works (history visible).

### Card 3.1 — Rep dashboard & profiles
**Depends on:** 3.0. Portal home lists the **organization's** registrations (any rep's, status badges) + grant status; org profile page (active reps edit: website, admissions office/email/phone, address, logo); personal profile: name, phone (E.164 validation), sms_opt_in.
**Tests:** rep sees own org's registrations only; pending/retired rep cannot edit org profile; opt-in persists; phone normalization; logo upload validation.

### Card 3.2 — Registration wizard
**Depends on:** 2.3, 3.0. Filament wizard on the Rep panel: step 1 confirm/update org details, step 2 rep contact + SMS opt-in, step 3 payment — shows grant-aware price (`Event::priceFor`), then: free → immediate confirmation screen; else choose Stripe (redirect placeholder until 4.1) or check (status pending_payment, queue instructions per 4.2).
**Tests:** full wizard walk via Livewire tests; validation per step; active-member gate; grant price displayed and enforced server-side; free path confirms without payment step; closed event blocks entry.

### Card 3.5 — Grant application (portal)
**Depends on:** 2.6, 3.0. Portal page/action per open or upcoming event: apply with justification; status timeline (pending/approved+benefit/denied+reason); withdraw while pending. **Use the approved form + copy verbatim from doc 01 Appendix A.**
**Tests:** active-member gate; one application per org+event; withdraw; status display; notifications queued.

### Card 3.3 — Receipts & registration detail
**Depends on:** 3.2. Detail page: status timeline, payment info, receipt download (PDF), retry-payment button when pending Stripe.
**Tests:** authorization (other reps 403), receipt renders for confirmed only.

### Card 3.4 — Interest form ("notify me")
**Depends on:** 1.2. On closed-event pages: email + optional organization-name capture → `event_interests` (unique per event+email), honeypot + rate limit.
**Tests:** stores row, dedupes, rate-limited.

## Phase 4 — Payments

### Card 4.1 — Stripe Checkout
**Depends on:** 3.2. Implement `StripeCheckoutService::createSession()` per 04; wire wizard Stripe branch; success/cancel URLs; pending Payment row.
**Tests:** fake gateway asserts amount from the registration's grant-aware `price_cents` snapshot (never input), metadata, payment row created; wizard redirects; free registrations never reach the gateway.

### Card 4.2 — Check flow + PDF
**Depends on:** 3.2. `CheckPaymentService`; dompdf printable form (organization, event, grant-aware amount, mailing address from doc 00); `RegistrationCheckInstructions` mailable; admin "Mark check received" action (check #, date) → confirm + receipt.
**Tests:** PDF generates with correct fields; action transitions status and queues receipt; only admins.

### Card 4.3 — Stripe webhook + refunds
**Depends on:** 4.1. Webhook route per 04 (signature verify, idempotency ledger, handlers for completed/expired/refunded); admin Refund action calling `refund()`.
**Tests:** signed fixture accepted / bad signature 400 / duplicate event no-ops; completed confirms + queues notifications; amount-mismatch flags not confirms; refund transitions.

## Phase 5 — Public site

### Card 5.1 — Layout & theme
**Depends on:** 1.1. Public layout with fair branding via Filament theme/components; nav (About, Representatives, Sponsors, FAQ, Contact); footer with contact block from doc 00.
### Card 5.2 — Home, About, Sponsors, FAQ pages
**Depends on:** 5.1, 2.4. Render from core content blocks, sponsors, faq_items. FAQ includes map embed + W-9 download.
### Card 5.3 — Representatives + Last Year rosters
**Depends on:** 2.3. `RosterService`: confirmed + `show_on_roster` for current event; previous published event for Last Year (fixes staleness gap, R1.4). **Rosters display org logos** (initial-letter placeholder when unset — R1.3); lazy-loaded images with proper alt text.
### Card 5.4 — Event pages + contact form
**Depends on:** 5.1, 3.4. `/events/{slug}`: details, price, status-aware CTA (register / closed+interest form / not yet open). Contact page via laravel-core's contact module (`core.contact.routes` or component embedded in our page; recipients = coordinator; consent checkbox added on our side; core provides honeypot + throttle + storage + receipt).
**Tests (all of phase 5):** HTTP tests per page (200, key content, roster correctness incl. hidden/unpaid exclusions, CTA states by registration window), contact submission stored in core_contact_submissions + organizer mail queued.

## Phase 6 — Communications (design: doc 07)

### Card 6.0 — Themed email layout + EmailLog enablement
**Depends on:** 1.1. Build `resources/views/emails/layout.blade.php` + components per doc 07 §1 (brand tokens in `config/fair.php`); override `core::emails.layout` so package mail matches; enable `core.email_log` (store_body, prune ≥ 400 days) and schedule `core:prune-email-logs`; local-only mail-preview route.
**Tests:** layout renders (header/footer/contact block); campaign footer line present on broadcast-stream mail; core override applied; EmailLog captures an app mailable end-to-end (array transport).
### Card 6.1 — Notification classes
**Depends on:** 6.0, 1.4. All classes from R4 matrix + `SmsChannel`; Postmark streams per 04; all email via the themed layout.
**Tests:** `Notification::fake()` — right classes, right channels (SMS only when opted in), right stream headers.
### Card 6.2 — Admin alerts
**Depends on:** 6.1, 4.3. New registration + payment received → email + SMS to `ADMIN_ALERT_*` (config toggle).
### Card 6.3 — AudienceBuilder
**Depends on:** 1.2. `App\Services\AudienceBuilder` per doc 07 §2: all enum cases, filters, dedupe rules, at-send-time semantics.
**Tests:** the truth table in doc 07 §6 — every case incl. lapsed subtraction, cancelled exclusion, dedupe by user/email, manual-entry contact fallback.
### Card 6.4 — Campaign composer + tracking
**Depends on:** 6.0, 6.1, 6.3, 2.2. Message Filament resource per doc 07 §3 (compose, audience picker with live preview count + recipient list, test-send-to-me, send now/scheduled); `SendEventBroadcast` job freezing `message_recipients`; `X-CTC-Recipient-Id` header + `EmailLogged` listener linking `email_log_id`; delivery table + totals on the message page; single-recipient resend via core.
**Tests:** end-to-end campaign with EmailLog enabled (doc 07 §6); listener correlation under concurrent sends; missing-header no-op; scheduled dispatch via `schedule:run`; SMS only to opted-in.
### Card 6.5 — Interest-list announcement
**Depends on:** 6.1, 3.4. Admin action on Event: "announce registration open" → `RegistrationOpenAnnouncement` to un-notified interests; stamps `notified_at`.
**Tests:** only un-notified receive; stamp set; second run no-ops.
### Card 6.6 — Historical roster import
**Depends on:** 1.2. Import the 2025/2026 organizations + rep emails from the owner's ISPEUS/site export (format TBD — doc 01 open question) as organizations (+ admissions contacts where known) + manual registrations on past events, so cross-year audiences are real at launch. Artisan command + idempotent re-run.
**Tests:** import from fixture file; re-run doesn't duplicate; imported reps resolve in `LastEvent`/`LapsedAnyPrevious` audiences.

## Phase 7 — Launch hardening

### Card 7.1 — Security & privacy pass
Rate limits (auth, interest; contact throttle via core config), policy/permission audit on every resource/action, `stripe_webhook_events` pruning command, pruning schedule for contact submissions (`core:prune-contact-submissions`), email logs (`core:prune-email-logs`), and message recipients per the 24-month promise (N3), headers (HSTS etc.), `core:doctor` in deploy pipeline.
### Card 7.2 — Full test & fixture review
Coverage review against doc 06 inventory; browser smoke of both panels + wizard; seed a realistic 2027 event.
### Card 7.3 — Deployment & ops runbook
Write `docs/07-deployment.md`: host setup, queue worker, cron, Postmark domain verification, Stripe live webhook, Twilio A2P registration, backup policy, go-live checklist including DNS cutover from ISPEUS.

---

## Suggested first session

Cards 1.1 → 1.2 → 1.3 → 1.4 fit comfortably in one focused session and unblock everything else.
