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
> 4. UI is split (owner directive 2026-08-19, superseding 2026-08-16): the **public site is Blade +
>    Livewire + Flowbite** on Tailwind 4; the **`/admin` backend is Filament**. No public-facing Filament
>    panel. Phase 5 was built under the old directive and its output is queued for rework — see doc 02.
> 5. If a card conflicts with reality (package versions, Filament API changes), fix forward and record the
>    deviation in this file under the card.

## Phase checklist

- [x] Phase 1 — Foundation (cards 1.1–1.4) — **complete 2026-08-18**
- [x] Phase 2 — Domain & admin core (cards 2.1–2.6) — **complete 2026-08-18**
- [x] Phase 3 — Rep portal & registration (cards 3.0–3.5) — **complete 2026-08-18**
- [x] Phase 4 — Payments (cards 4.1–4.3) — **complete 2026-08-18**
- [x] Phase 5 — Public site (cards 5.1–5.4) — **complete 2026-08-19**
- [x] Phase 6 — Communications (cards 6.0–6.6) — **complete 2026-08-19**
- [x] Phase 7 — Launch hardening (cards 7.1–7.3) — **complete 2026-08-19**
- [ ] Phase 8 — Public site in Blade/Livewire/Flowbite (cards 8.0–8.5) — **8.0 done 2026-08-19; the rest waits on the design handoff**

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

**Status: done (2026-08-18).** Shipped: `RoleSeeder` (already present), `CoordinatorSeeder`,
`ContentBlockSeeder` (nine core `block` rows from doc 00's copy), `SponsorSeeder` (the four schools),
`FaqSeeder` (eleven questions), `EventSeeder` (2025, 2026, 2027), `FairFixtureSeeder` (dev-only), and
`ProductionSeeder` alongside the dev `DatabaseSeeder`. New `config/fair.php` holds the contact block,
coordinator identity, brand tokens and admin-alert settings — one source for values that must match
across a Filament panel, a public page and an email. 20 Pest tests in
`tests/Feature/Foundation/SeederTest.php`; suite 164, Pint clean.

**Decisions taken (detail in [10-implementation-decisions.md](10-implementation-decisions.md)):**
`CoordinatorSeeder` only sets a known password in local/testing — anywhere else it sets 64 random
characters and tells the operator to send a reset (D-1.3-a). The 2027 event seeds **unpublished**,
because its date and price are placeholders and an unpublished event cannot take money (D-1.3-b);
`FairFixtureSeeder` publishes and opens it in development only. Every seeder is idempotent by a
natural key and never overwrites edited copy (D-1.3-c). FAQ answers doc 00 does not contain are
seeded as explicit `TODO-OWNER` rows rather than invented (D-1.3-d).

**Owner queue — copy that needs you:** the refund/cancellation policy (`policy.refunds` content
block), the parking/unloading directions, the hotel list, the conduct guidelines, the W-9 PDF, and
the real 2027 date and price. All are editable in the admin panel; none need a deploy.

### Card 1.4 — Service scaffolding & config
**Depends on:** 1.2.
**Do:** `SmsService` interface + `TwilioSms` + `NullSms`; `PaymentGateway` interface + empty `StripeCheckoutService`; `RegistrationService`, `RosterService` shells; container bindings (env-dependent); add all env keys from doc 02 to `.env.example`; `config/services.php` entries; install `stripe/stripe-php`, `twilio/sdk`, `barryvdh/laravel-dompdf`.
**Tests:** container resolves interfaces to correct implementations per env config; `NullSms` records/logs.

**Status: done (2026-08-18).** Shipped: `App\Services\Sms\{SmsService, SmsResult, TwilioSms, NullSms}`;
`App\Services\Payments\{PaymentGateway, CheckoutSession, StripeCheckoutService}`;
`App\Services\{RegistrationService, RosterService}`; container bindings in `AppServiceProvider`;
`config/services.php` entries for Stripe, Twilio and the two Postmark streams; the full env key set in
`.env.example`; `Tests\Fakes\FakePaymentGateway`. Installed `stripe/stripe-php ^21.2`,
`twilio/sdk ^8.12`, `barryvdh/laravel-dompdf ^3.1` (owner approved, standing answer A1).
17 tests in `tests/Feature/Foundation/ServiceBindingTest.php`; suite 181, Pint clean.

**Decisions (detail in [10-implementation-decisions.md](10-implementation-decisions.md)):** the SMS
binding is keyed on *complete* credentials, so a half-configured Twilio account degrades to `NullSms`
rather than to a client that throws inside a queued notification (D-1.4-a). The Stripe binding is
**not** conditional — there is no safe silent fallback for taking money (D-1.4-b). The unbuilt
`StripeCheckoutService` methods throw naming their card rather than returning something plausible
(D-1.4-c). `RosterService` is fully implemented already, because it is only model scopes composed
(D-1.4-d). The stale `services.postmark.key` entry was replaced with `token`, which is the key
Laravel's transport actually reads first (D-1.4-e).

## Phase 2 — Domain & admin core

### Card 2.1 — Event resource (admin)
**Depends on:** 1.2. Filament resource: CRUD per R3.2, slug auto-generation, money input (dollars ↔ cents), registration window pickers, publish toggle. Policy: admin only.
**Tests:** create/edit via Livewire resource tests; validation (closes_at after opens_at, price ≥ 0); policy enforcement.

**Status: done (2026-08-18).** Shipped: `App\Filament\Admin\Resources\EventResource` with its four
pages, `App\Policies\EventPolicy`, and `App\Support\Money` (the one place dollars become cents and
back). 18 tests in `tests/Feature/Admin/EventResourceTest.php`; suite 199, Pint clean.

**Two pieces of test infrastructure landed with this card, and every later Filament test needs them:**

- `livewire()` in `tests/Pest.php`. `pestphp/pest-plugin-livewire` has no Pest 5 release, so the
  helper doc 06's examples assume is defined by hand over `Livewire::test()`.
- `usingAdminPanel()` / `usingRepPanel()`. Neither panel is marked `->default()` — this app has an
  admin panel and a rep portal and neither outranks the other — so mounting a Filament page directly
  in a test dies with "No default Filament panel is set". At runtime the panel middleware sets it
  from the route; in a test these do.

**Also fixed here:** `UserFactory::coordinator()` granted only `admin.access`, not the app's own
permissions. Every admin resource test would have failed as a 403 that looked exactly like a policy
bug. It now syncs the full permission set, which is what `RoleSeeder` does and what production
therefore looks like.

**Decisions:** the fee field converts through `formatStateUsing`/`dehydrateStateUsing` on the
component rather than through page-class hooks (D-2.1-a); slug suggestion runs on create only
(D-2.1-b); a fair with registrations against it cannot be deleted (D-2.1-c).

### Card 2.2 — Organization & Registration resources (admin)
**Depends on:** 2.1. Organizations (R3.3a): CRUD incl. profile fields + logo upload, rep list with membership statuses, approve/deny pending claims, retire reps, **merge-duplicates action** (repoints users/registrations/grants). Registrations: list with filters (event, status, method), search, detail view showing grant/price snapshot, `show_on_roster` toggle, notes, cancel action, resend-confirmation action (queues notification), CSV export. Manual-entry creation (user_id null).
**Tests:** filters/search return expected rows; claim approve/deny transitions + notifications queued; retire; merge repoints all relations; cancel sets status+timestamp; export contains expected columns; policies.

**Status: done (2026-08-18).** `OrganizationResource` (+ `RepresentativesRelationManager`),
`RegistrationResource` (+ CSV export and cancel action), `OrganizationService`,
`MembershipNotAllowed`, three membership events, and policies for both models. 16 + 14 tests in
`tests/Feature/Admin/`, 20 in `tests/Unit/Services/OrganizationServiceTest.php`.

**Decisions (doc 10):** membership and merge live in `OrganizationService`, which card 3.0's portal
will call too (D-2.2-a); merge repoints before deleting and reports registration collisions rather
than resolving them, because choosing between two paid registrations is a decision about money
(D-2.2-b); the registration edit form exposes only roster visibility, notes and the fair contact
(D-2.2-c); manual entry runs through the service (D-2.2-d); CSV export is streamed so it honours the
table's filters (D-2.2-e).

### Card 2.3 — RegistrationService
**Depends on:** 1.4, 2.1. `create()` (duplicate rules R2.7: hard-block same organization+event non-cancelled; acting rep must be an ACTIVE member; warning flag on normalized-name match at org creation), price snapshot via `Event::priceFor()`, **free-grant path confirms immediately with no payment**, `confirmPayment()`, `cancel()`, capacity enforcement.
**Tests:** unit — duplicate block, pending/retired rep rejected, capacity full rejection, free-grant immediate confirm (no payment row, receipt queued), discounted price snapshotted, confirm transitions + confirmed_at, cancel rules, event-closed rejection.

**Status: done (2026-08-18).** `App\Services\RegistrationService` with `create()`,
`createManualEntry()`, `confirmPayment()`, `cancel()` and `alreadyRegistered()`;
`App\Exceptions\RegistrationNotAllowed`; three domain events. 41 tests in
`tests/Unit/Services/RegistrationServiceTest.php`; suite 281, Pint clean.

**Decisions (doc 10):** the services fire domain events instead of sending mail, and card 6.1 hangs
the comms matrix off them (D-2.3-a); `createManualEntry()` is a separate method rather than a
nullable actor, so skipping the membership gate has to be asked for by name (D-2.3-b);
`confirmPayment()` is idempotent, because Stripe redelivers and a second receipt is what schools
notice (D-2.3-c); creation runs in a transaction so two reps registering in the same second cannot
both win (D-2.3-d).

### Card 2.6 — GrantService + Grants resource (admin)
**Depends on:** 1.2, 2.1. `GrantService`: `apply()` (active rep, one non-withdrawn application per org+event, only while event registration is open or upcoming), `approve()` (coordinator picks benefit: free / custom price / percent off), `deny()` (reason required), `revoke()` (only while unused). Filament Grants resource (R3.3b): review queue filtered by event/status, approve/deny/revoke actions, usage indicator. Decision notifications (R4).
**Tests:** application rules; approve sets benefit + queues rep email; deny requires reason; revoke blocked once used; permissions.

**Status: done (2026-08-18).** Resource shipped alongside the service: `App\Services\GrantService`
implements apply / approve / deny / revoke / withdraw plus `hasLiveApplication()` and
`currentApplication()`, with `App\Exceptions\GrantNotAllowed` and five domain events. 41 tests in
`tests/Unit/Services/GrantServiceTest.php`.

**Decisions:** applications close when the fair happens, not when registration does — a school
lining funding up early is the point (D-2.6-a); `approve()` validates the benefit parameters rather
than trusting the form, because an incomplete benefit silently falls through to list price
(D-2.6-b); only withdrawal frees the one-per-fair slot (D-2.6-c).

### Card 2.4 — Sponsors & FAQ resources (admin)
**Depends on:** 1.2. CRUD + `sort_order` reordering. (Content blocks and the contact inbox are laravel-core resources — configure/verify, don't build.)
**Tests:** resource CRUD, reorder persists; core content + contact resources reachable by coordinator.

**Status: done (2026-08-18).** `SponsorResource` (+ `StaffRelationManager`, drag reordering) and
`FaqItemResource` (drag reordering, a badge that surfaces the seeded `TODO-OWNER` answers).
laravel-core's Content and Contact resources are verified as registered and reachable rather than
rebuilt, and a test asserts this app has no parallel `ContentBlock` or `ContactSubmission` model.
12 tests in `tests/Feature/Admin/ContentResourcesTest.php`.

### Card 2.5 — Admin dashboard widgets
**Depends on:** 2.2. Widgets: confirmed count vs. capacity, revenue collected vs. pending-check amounts, recent registrations. All for the "active" (next upcoming published) event.
**Tests:** widget queries against seeded fixtures return correct numbers.

**Status: done (2026-08-18).** `ActiveFairOverview` (confirmed schools vs. capacity, collected,
awaiting payment) and `RecentRegistrations`, both scoped to the active fair. 10 tests in
`tests/Feature/Admin/DashboardWidgetsTest.php`. Suite 368, Pint clean. **Phase 2 complete.**

**Decisions:** revenue is summed from the registration price snapshots rather than the payments
table, because a free registration has no payment row and summing payments would report a
grant-heavy year as a bad one (D-2.5-a); both widgets show the active fair only, and an empty table
rather than every registration ever taken when nothing is published (D-2.5-b).

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

**Phase 3 status: done (2026-08-18).** Suite 431, Pint clean.

| Card | Shipped |
|---|---|
| 3.0 | `App\Filament\Rep\Pages\Auth\Register` (create-or-claim signup), `OrganizationService::createWithFounder()` / `claim()`, `OrganizationCreated` + `MembershipClaimed` events, self-retire on the profile page |
| 3.1 | Rep `RegistrationResource` (school-scoped list + detail), `OrganizationProfile` page, `Auth\EditProfile` (phone, SMS opt-in, self-retire), `App\Support\Phone`, `ActsForAnOrganization` concern |
| 3.2 | Three-step wizard on the rep `CreateRegistration` page |
| 3.3 | `ReceiptPdf` + `resources/views/pdf/receipt.blade.php`, download action on the detail page |
| 3.4 | `EventInterestController`, `StoreEventInterestRequest` (honeypot), throttled route |
| 3.5 | Rep `GrantResource` + apply/withdraw actions, doc 01 Appendix A copy verbatim |

**Decisions (doc 10, D-3.x):** portal authorization lives on the rep resources rather than in the
policies, which answer a coordinator's question rather than a school's (D-3.1-a); another school's
registration is a 404, not a 403, so the response does not confirm the row exists; the portal lists
the *school's* registrations so a new admissions officer inherits the history rather than an empty
page (D-3.1-b); phone numbers are normalised rather than rejected, and having one is not consent to
be texted (D-3.1-c); the wizard displays the price and has no field for it (D-3.2-a); the receipt
renders from the snapshot and only once confirmed (D-3.3-a); the interest form dedupes
case-insensitively behind a honeypot and an IP throttle (D-3.4-a).

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

**Phase 4 status: done (2026-08-18).** Suite 470, Pint clean.

| Card | Shipped |
|---|---|
| 4.1 | `StripeCheckoutService::createSession()` / `refund()`, wizard redirect to Checkout, `pay` retry action on the portal detail page |
| 4.2 | `CheckPaymentService`, `CheckPaymentForm` + `resources/views/pdf/check-form.blade.php`, `markCheckReceived` admin action |
| 4.3 | `routes/webhooks.php`, `StripeWebhookController`, `StripeWebhookHandler`, `refund` admin action |

**Still owed by this phase:** the `RegistrationCheckInstructions` mailable that attaches the printed
form. Deferred to card 6.1 with the rest of the comms matrix (doc 10, D-2.3-a) — the PDF itself is
built and downloadable from the portal today, so the check path is usable without it.

**Decisions (doc 10, D-4.x):** the gateway takes a registration and has no amount parameter, so the
charged figure and the quoted figure are the same number by construction (D-4.1-a); Checkout is
opened after the row is saved, so a Stripe outage leaves a recoverable registration rather than
losing one (D-4.1-b); recording a check confirms in the same transaction and through the same
`confirmPayment()` the webhook uses (D-4.2-a); the `charge.refunded` webhook owns the refund
transition, so a refund from the Stripe dashboard and one from our panel agree (D-4.3-a);
idempotency is claimed before any work and a handler failure still answers 200, because a 500 makes
Stripe retry for three days (D-4.3-b); an amount mismatch flags and refuses to confirm (D-4.3-c).

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

**Phase 5 status: done (2026-08-18/19).** Suite 500, Pint clean.

The public site is a **third Filament panel** (`SitePanelProvider`, path `''`, no login, no
`Authenticate` middleware) rather than Blade views — the strictest reading of the Filament-only
directive. **This is the piece most likely to want your eye:** a Filament panel is an application
shell, and the visual design of a public marketing site is the owner's call. See doc 10, D-5.1-a,
which also records how contained a change back to Blade-plus-Filament-components would be.

| Card | Shipped |
|---|---|
| 5.1 | `SitePanelProvider` (top navigation, shared palette), `RendersContentBlocks` concern |
| 5.2 | `Home`, `About`, `Sponsors`, `Faq` pages |
| 5.3 | `RosterTable` + `CurrentRoster` / `PreviousRoster` widgets, `Representatives` and `LastYear` pages |
| 5.4 | `EventPage` (state-aware CTA + inline interest form), `Contact` embedding `<x-core::contact-form />` |

**Decisions (doc 10, D-5.x):** a missing content block renders as nothing rather than a placeholder
(D-5.2-a); one roster widget serves both pages, which is the fix for the staleness bug doc 00
recorded (D-5.3-a); the roster renders with the page rather than lazily, so search engines and
no-JavaScript visitors can read it (D-5.3-b); the missing-logo placeholder is a generated inline SVG
rather than a third-party avatar service (D-5.3-c); the contact consent is a stated notice rather
than an unvalidated checkbox, because making it real needs a change in `laravel-core` and this app
must not edit a sibling project (D-5.4-a — **owner decision needed**); an unpublished fair is a 404,
not a 403 (D-5.4-c).

**Still owed:** the FAQ's Google Map embed and W-9 download (card 5.2). Both wait on owner content —
the map URL and the signed PDF — and the FAQ rows carrying `TODO-OWNER` are where they land.

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

**Phase 6 status: done (2026-08-19).** Suite 588, Pint clean.

| Card | Shipped |
|---|---|
| 6.0 | `resources/views/emails/` theme (layout + button/panel/roster-line), `core::` layout override, `RendersThemedMail` trait |
| 6.1 | `PaymentReceipt`, `RegistrationCheckInstructions`, `GrantDecided`, `MembershipDecided`, `RegistrationOpenAnnouncement`, `AdminAlert`, `SmsChannel`, and the listeners wired in `EventServiceProvider` |
| 6.2 | `AdminAlerts` (one place answering "who is the coordinator"), email + opt-in SMS |
| 6.3 | `AudienceBuilder` + `RecipientDto` — all eight cases, filters, dedupe, generic fallback |
| 6.4 | `MessageResource` composer (live preview count, recipient list, test send, send/schedule), `SendEventBroadcast`, `LinkEmailLogToRecipient`, delivery relation manager, `fair:send-scheduled-campaigns` |
| 6.5 | "Tell the interest list" action on the fair page |
| 6.6 | `fair:import-roster` + the documented CSV schema (owner has no export yet — standing answer A3) |

**Two Blade traps found the hard way, both now commented in the views (doc 10, D-6.0-a/b):** a
prefixed anonymous component path does not resolve a nested `<x-emails::components.panel>`, and a
double quote inside a PHP expression in a component attribute closes the attribute early. Both leave
the tag uncompiled and print raw Blade into the email, silently.

**Also:** `phpunit.xml` now sets `memory_limit` to 512M — dompdf renders in a couple of dozen tests
exhaust the 128M default in one process. Worth carrying into the queue worker's configuration
(card 7.3).

**Owner queue:** the ISPEUS export for card 6.6, and the brand colour and logo URL in
`config/fair.php` (the layout falls back to the app name in text until then).

## Phase 7 — Launch hardening

### Card 7.1 — Security & privacy pass
Rate limits (auth, interest; contact throttle via core config), policy/permission audit on every resource/action, `stripe_webhook_events` pruning command, pruning schedule for contact submissions (`core:prune-contact-submissions`), email logs (`core:prune-email-logs`), and message recipients per the 24-month promise (N3), headers (HSTS etc.), `core:doctor` in deploy pipeline.
### Card 7.2 — Full test & fixture review
Coverage review against doc 06 inventory; browser smoke of both panels + wizard; seed a realistic 2027 event.
### Card 7.3 — Deployment & ops runbook
Write `docs/07-deployment.md`: host setup, queue worker, cron, Postmark domain verification, Stripe live webhook, Twilio A2P registration, backup policy, go-live checklist including DNS cutover from ISPEUS.

**Phase 7 status: done (2026-08-19).** Suite 609, Pint clean.

| Card | Shipped |
|---|---|
| 7.1 | `SecurityHeaders` middleware, `fair:prune-stripe-events`, `fair:prune-message-recipients`, the full pruning schedule, and a permission audit asserted against actions rather than navigation (`tests/Feature/Foundation/SecurityTest.php`) |
| 7.2 | `tests/Feature/SmokeTest.php` — every GET route in all three panels, discovered from the router |
| 7.3 | [11-deployment.md](11-deployment.md) |

**No Content-Security-Policy, deliberately** (doc 10, D-7.1-a): Filament ships inline styles and
Alpine expressions, so a CSP tight enough to matter would break the admin panel and a loose one
would be decoration. Card payments never touch our origin. Worth a deliberate revisit at a real
security review rather than a ten-minute addition.

**The smoke test earned its place immediately** — it found `/admin/messages/create` returning 500 on
`Select::descriptions()`, a Filament `Radio` method. No resource test had opened that page. It is an
HTTP sweep rather than the browser pass the card asks for (D-7.2-a); a real browser pass before
launch is on doc 11's go-live checklist.

---

## Phase 8 — Rebuild the public site in Blade + Livewire + Flowbite

**Owner directive, 2026-08-19.** Frontend UI is Blade + Livewire + Flowbite on Tailwind 4; Filament
is the admin backend only. This supersedes the 2026-08-16 "all UI is Filament" directive that
Phase 5 was built under. **The rep portal at `/portal` stays Filament** — owner confirmed
2026-08-19, so this phase touches the public site alone. Background and the full reasoning are in
doc 10, D-5.1-a.

**The design handoff landed on 2026-08-19** and lives in
[`docs/design-handoff/`](design-handoff/) — a landing page, an interior page, a
maintenance page, and a README that declares colours, typography, spacing and copy final. Cards
8.1–8.5 were built against it. Two structural readings were confirmed with the owner before
building: the landing page is **Home** (not a one-pager — the interior layout serves the other six
pages), and **"Representatives" keeps meaning the public roster**, not a registration call to
action.

### Card 8.0 — Front-end wiring

**Status: done (2026-08-19).** `flowbite` as a runtime dependency, `@plugin 'flowbite/plugin'` and
the three `@source` lines Tailwind v4 cannot auto-detect in `resources/css/app.css`,
`import 'flowbite'` in `resources/js/app.js`, matching the `ckbs` reference wiring.

`config/livewire.php` published with `component_layout => 'components.layouts.app'`. Livewire 4 ships
`layouts::app`, and that namespace does **not** resolve here — `component_namespaces` registers
namespaces for Livewire's component resolution, not Blade view hints, so the first full-page
component without a `#[Layout]` attribute would have died with "No hint path defined for [layouts]".
The layout at that path is a deliberate **placeholder**: `@vite`, a slot, a title, and no design
whatsoever. The handoff replaces it.

`APP_URL` was pointing at `coasttocoastcollegefair.test`, which Herd does not serve — the site is
`https://coasttocoast.test`. Harmless while every page came from Filament's published assets; fatal
the moment a Blade page calls `@vite`, which emits absolute URLs built from `APP_URL`. Fixed in the
local `.env`. **`APP_URL` must match the serving host in every environment.**

8 tests in `tests/Feature/Foundation/FrontendWiringTest.php` pin all of it, plus the Filament asset
publishing that D-8-a fixed. None of them assert anything about how the site looks.

### Card 8.1 — The layout and chrome

**Status: done (2026-08-19).** `resources/views/components/layouts/app.blade.php` is the real layout:
skip link, `<x-site.header />`, an unwrapped `<main id="main">` so a full-bleed section can reach the
viewport edge, and `<x-site.footer />`. The chrome lives in `resources/views/components/site/`
(`header`, `footer`, `container`, `page-header`) and the reusable primitives in
`components/ui/` (`button`, `eyebrow`, `section-heading`, `prose`, `field`, `alert`). The header's
mobile drawer is Flowbite's `data-collapse-toggle`; the "Log in" and "Register" links point at the
rep panel's own auth routes.

Fonts are **self-hosted** rather than loaded from the handoff's Google Fonts `<link>`: `vite-plugin-webfont-dl`
via `bunny()` in `vite.config.js` for Montserrat, Caveat and Source Sans 3. A public marketing site
should not make every visitor issue a third-party request, and self-hosting removes a render-blocking
round trip to another origin. See doc 10, D-8.1-a.

**Original card.** Replace `resources/views/components/layouts/app.blade.php` with
the real layout: header, public navigation (Home, About, Representatives, Last Year, Sponsors, FAQ,
Contact), footer carrying the contact block from `config('fair.contact')` — the same source the
email footer and the check PDF read, so an address change lands everywhere at once. Flowbite's
navbar handles the mobile toggle.

### Card 8.2 — The static pages

**Status: done (2026-08-19).** `App\Http\Controllers\SiteController` with `home`, `about`,
`sponsors`, `faq`, `contact` and `event` actions, rendering `resources/views/site/*`. The trait
became `App\Support\ContentBlocks::render()` — a static helper rather than a concern, because the
callers are now controllers rather than a shared Page base class. **Its behaviour is unchanged,
including a missing or empty block rendering as nothing at all** (D-5.2-a).

The landing page's prose is editable copy, not strings in the template: `home.hero` and
`home.for_representatives` were reseeded with the handoff's final wording and the view renders them
through `ContentBlocks`. Hard-coding the design's paragraphs would have removed the coordinator's
ability to change them without a deploy and orphaned two content blocks. The headline and section
titles stay in the template — they are display type, sized and cropped to the layout.

Fixing that surfaced a real defect in `ContentBlockSeeder`: core's `Content` soft-deletes, its unique
index is `(type, slug)` with no `deleted_at`, so a block the coordinator deleted still owns its slug.
The guard asked the default scope, called the slug free, and the next deploy's seed would have died
on a UNIQUE violation. The guard is now `withTrashed()`, with a test.

**Original card.** Home, About, Sponsors and FAQ as plain Blade views behind controller actions.
Each one's content already exists as a Filament `content()` method under `app/Filament/Site/Pages/`;
the data-gathering moves almost unaltered and only the rendering changes.

`RendersContentBlocks` (doc 10, D-5.2-a) is a data source, not UI — keep its behaviour, **including
a missing block rendering as nothing at all**, but the `fi-prose` wrapper becomes Flowbite/Tailwind
typography.

### Card 8.3 — The rosters

**Status: done (2026-08-19).** `App\Livewire\RepresentativesRoster` and `LastYearRoster`, both over
`App\Livewire\Concerns\ShowsARoster` — one implementation serving both pages, against different
fairs, which is the whole point of D-5.3-a. Server-rendered first paint, search, pagination at 30,
the initial-letter placeholder and lazy-loaded logos with real alt text all survive.

**Original card.** Representatives and Last Year. The natural Livewire candidates — they want
search and pagination — and the one place to be careful: `RosterService` and the shared
current/previous split (doc 10, D-5.3-a) exist because the live site's Last Year page was showing
the *current* roster. **Keep one component serving both pages.** Keep the initial-letter placeholder
(D-5.3-c) and lazy-loaded images with real alt text.

The roster must render server-side, not after a round trip (D-5.3-b): a search engine, and a rep
checking whether their school is already listed, both need it in the HTML.

If a roster swaps DOM after load, Flowbite's initialisers need re-running — `initFlowbite()` in a
`livewire:navigated` listener. Noted in `resources/js/app.js`.

### Card 8.4 — Event pages and the contact form

**Status: done (2026-08-19).** `App\Livewire\ContactForm` and `App\Livewire\EventInterest`, with
their logic carried across rather than reimplemented: `ContactService::submit()`, `accepted()` on the
consent checkbox, the honeypot, and the per-IP throttle. The interest capture keeps its plain POST
route as the non-JavaScript path. The event page keeps its three CTA states and an unpublished fair
is still a 404.

`App\Livewire\EventCountdown` is new to this phase — the handoff's landing page has one. It does
**not** `wire:poll`: a one-second poll on a public marketing page is one request per visitor per
second. The ticking is an Alpine `setInterval` over a server-rendered first paint, so the numbers are
correct before JavaScript runs and correct without it. See doc 10, D-8.4-a.

**Original card.** The event page keeps its three CTA states (open → register; not yet open → the
date to diarise; closed → the interest form). That state machine is the fix for the current site's
dead end and is covered by tests that should survive the move. An unpublished fair stays a 404
(D-5.4-c).

The contact form and the interest capture both become Livewire components. **Their logic carries
over verbatim and must not be reimplemented:** `ContactService::submit()` does the work, the consent
checkbox is validated with `accepted()`, and the honeypot and IP throttle stay (doc 10, D-8-d,
D-5.4-b). The interest capture also keeps its plain POST route as the non-JavaScript path.

### Card 8.5 — Retire the panel

**Status: done (2026-08-19).** `SitePanelProvider`, the eight `Page` classes, the three roster
widgets and the `RendersContentBlocks` concern are deleted, and `bootstrap/providers.php` lost the
registration-order comment that existed only because the site panel claimed the root. Doc 02's
"Public pages" row and panel table, this file, and golden rule 1 in `docs/README.md` are updated.

A test now asserts the negative directly — the landing page carries no `class="fi"` and loads no
`/css/filament/` stylesheet, so a future session cannot quietly reintroduce a public panel and have
the suite stay green.

The **maintenance page** from the handoff also landed here, as `resources/views/errors/503.blade.php`.
It is deliberately self-contained — inline styles, static image paths, no `@vite` — because
`artisan down --render=errors::503` freezes the rendered HTML to a flat file for the whole outage,
which is exactly when `public/build` is being replaced.

**Original card.** Delete `SitePanelProvider`, the eight `Page` classes under
`app/Filament/Site/Pages/`, the roster widgets, and `resources/views/filament/admin/audience-preview`
if nothing else uses it. Remove the registration-order comment in `bootstrap/providers.php`, which
exists only because the site panel claimed the root.

**The public tests are already plain HTTP assertions** (`get('/faq')->assertSee(...)`), so most
should survive unchanged; the handful calling `livewire(SomePage::class)` need repointing. Update
doc 02 (its "Public pages" row and the panel table), this file, and golden rule 1 in
`docs/README.md` — all three still say Filament-only.

---

## Where this leaves the build (2026-08-19)

All eight phases are implemented and **630 tests pass** with Pint clean. The application is
functionally complete: registration, payments, grants, the admin panel, the rep portal, the whole
comms system, and now a public site built the way the owner directive asks for.

**No code work is outstanding.** What is left is content, credentials and a look at it in a browser:

1. **[11-deployment.md](11-deployment.md) has an owner content queue** — the 2027 date and price, the
   refund policy, parking, hotels, conduct guidelines, the W-9, and the ISPEUS roster export.
   Everything on it is editable in the admin panel or an env value; nothing needs a deploy.
2. **The design handoff leaves four assets outstanding**, listed at the bottom of doc 11: the four
   sponsor school logos, a transparent-background wordmark, a higher-resolution cityscape, and a map
   embed pinned to the venue rather than to Chattanooga generally. The site renders without them —
   a sponsor tile falls back to the school's name — but each is a visible gap.
3. **An address discrepancy needs the owner's word.** The design says "1 Carter Plaza"; doc 00 and the
   seed say "1150 Carter Street". The build kept 1150 Carter Street, because that is what the live
   site says today. Also in doc 11.
4. **A browser pass** before launch, per doc 11's checklist. The last one found four rendering faults
   that 609 passing tests had not (doc 10, D-8-a…d), so it is not a formality.

**Both decisions previously flagged for the owner are answered and closed.** D-5.1-a (the public site
as a Filament panel) was answered "rework it", and Phase 8 did it. D-5.4-a (contact consent) was
resolved by rebuilding the form so its checkbox is actually validated — see D-8-d.

**Still open and NOT to be acted on without asking:** whether the rep portal at `/portal` eventually
leaves Filament too. The owner confirmed on 2026-08-19 that it **stays Filament for now**; the
reasoning and the sibling precedent are in doc 10, D-5.1-a.

---

## Suggested first session

Cards 1.1 → 1.2 → 1.3 → 1.4 fit comfortably in one focused session and unblock everything else.
**That advice is historical** — every card in this file is done. A session picking this project up
today starts by reading doc 11's owner content queue and doc 10's decision log, not by writing code.
