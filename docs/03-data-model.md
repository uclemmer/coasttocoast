# 03 — Data Model

> **Purpose of this document:** Tables, relationships, enums, and factory/seed expectations.
> Any session adding a migration must update this file in the same commit.

## Entity overview

```
organizations ──< users (reps; membership pending|active|retired)
      │
      ├────────< registrations >────────── events
      │                │                      │
      ├────────< grants ─── (per event)       │<─ event_interests
      │                │<─ payments           │<─ messages ──< message_recipients ──> core_email_logs
      │           sponsors ──< sponsor_staff        faq_items

Provided by uclemmer/laravel-core (its published core_* migrations — do NOT recreate):
  core_roles / core_permissions (+ pivots)   → replaces users.is_admin (D6)
  core_contact_submissions                   → replaces our contact_submissions
  core_contents / core_content_revisions     → replaces our content_blocks (type `block`)
  core_email_logs                            → send tracking (doc 07)
  core_settings / core_profiles / core_job_metrics (as enabled)
```

An **organization** is the college/university (D8): it owns the profile, the registration history, and any
grants, and it persists across rep turnover. A **user** is a rep (belonging to exactly one organization, with
a membership lifecycle) or a coordinator (roles via laravel-core's `HasCoreRoles`; coordinators have no
organization). A **registration** joins an organization to an **event** (one fair year) and carries payment
state; a **grant** (D10) is an organization's per-event application for free/discounted registration.
**payments** records actual money movements (Stripe or check). Everything else supports content and
communications.

## Tables

### users

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| name | string | rep full name |
| email | string unique | |
| email_verified_at | timestamp null | Filament Rep-panel email verification |
| password | string | |
| phone | string null | E.164 |
| sms_opt_in | boolean default false | reminders only if true (privacy N3) |
| organization_id | fk organizations, null | the rep's org (D8: one org per rep); null for coordinators |
| membership_status | string enum null | `pending` \| `active` \| `retired` (null for coordinators) |
| membership_approved_at / retired_at | timestamp null | |
| retired_by | fk users null | self or coordinator (R2.10) |
| timestamps, rememberToken | | |

No `is_admin` column — admin access is a laravel-core role (`coordinator`) with permissions; `User` uses
`HasCoreRoles` (D6). Two-factor columns available via core's published stub if 2FA is enabled later.

**Membership rules:** creating a new org → `active` immediately (coordinator alerted); claiming an existing
org → `pending` until the coordinator approves (D9). Only `active` reps can register, apply for grants, or
edit the org profile. `retired` reps keep their account + history, lose org rights, and are excluded from
campaign audiences (doc 07). Helper scopes: `activeReps()`, `pendingReps()`.

### organizations (formerly "institutions" — D8)

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| name | string | display name |
| normalized_name | string index | lowercased/stripped for duplicate soft-check (R2.7) and audience matching |
| website | string null | |
| logo_path | string null | uploaded via Filament; public disk |
| admissions_office | string null | office/department name |
| admissions_email | string null | generic org contact — audience fallback when no active rep (doc 07) |
| admissions_phone | string null | |
| address_line1 / line2 / city / state / postal_code | strings (line2 null) | for W-9/receipt needs |
| created_by | fk users null | rep who created it; null = admin/import |
| timestamps | | |

No owner column — reps point at the org (`users.organization_id`), so the org survives rep turnover. Admin
gets a **merge-duplicates** action (repoint users/registrations/grants, keep oldest, delete husk — R3.3a).

### events

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| name | string | "College Fair 2027" |
| slug | string unique | `college-fair-2027` |
| starts_at / ends_at | datetime | fair open/close (6:30–8:00 PM) |
| reception_starts_at | datetime null | counselor reception |
| venue_name / venue_address | string | |
| price_cents | integer | e.g. 21500 |
| capacity | integer null | optional cap on confirmed registrations |
| registration_opens_at / registration_closes_at | datetime null | window control (R1.8) |
| is_published | boolean default false | |
| timestamps | | |

Helper accessors: `isRegistrationOpen()`, `isFull()`, `previous()` scope for the Last Year page (R1.4).

### registrations

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| event_id / organization_id / user_id | fks | user_id = the rep who registered; null for admin manual entries |
| status | string enum | `pending_payment`, `confirmed`, `cancelled`, `refunded` |
| payment_method | string enum null | `stripe`, `check`; **null for free (100% grant) registrations** |
| grant_id | fk grants, null | approved grant applied at creation (D10) |
| price_cents | integer | **snapshot** of the grant-aware price actually charged (N1) — 0 for free |
| rep_name / rep_email / rep_phone | strings (phone null) | contact for *this* fair (may differ from account) |
| show_on_roster | boolean default true | admin override (R3.4) |
| notes | text null | admin notes |
| confirmed_at / cancelled_at | timestamp null | |
| timestamps | | |

**Rules for implementers:** `RegistrationService::create()` must reject a second non-cancelled registration
for the same organization+event (service-level check; SQLite partial-unique support differs), require the
acting rep to be an `active` member of that organization, compute `price_cents` via `Event::priceFor($org)`
(never from input), and — when the result is 0 — confirm immediately with no payment row (R2.4).

### grants (D10 — per-event, coordinator-set benefit)

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| organization_id / event_id | fks | one application per org per event (unique, non-withdrawn — service check) |
| requested_by | fk users | active rep who applied |
| justification | text | from the application form |
| status | string enum | `pending`, `approved`, `denied`, `revoked`, `withdrawn` |
| benefit_type | string enum null | `free`, `custom_price`, `percent_off` — set at approval |
| custom_price_cents | integer null | when benefit_type = custom_price |
| percent_off | unsigned tinyint null | 1–100; 100 ≡ free in effect but store what was chosen |
| decided_by / decided_at | fk users null / timestamp null | |
| denial_reason | text null | included in the decision email |
| timestamps | | |

**Pricing (one source of truth):** `Event::priceFor(Organization $org): int` — the event's `price_cents`
unless the org holds an `approved`, unrevoked grant for that event, in which case: `free` → 0,
`custom_price` → `custom_price_cents`, `percent_off` → rounded down. Used by the wizard display, Stripe
session creation, and the check PDF; the result is snapshotted onto `registrations.price_cents`.
A grant can be revoked only while unused (no non-cancelled registration references it).

Enums added (`app/Enums`): `MembershipStatus`, `GrantStatus`, `GrantBenefit`.

### payments

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| registration_id | fk | |
| method | string enum | `stripe`, `check` |
| status | string enum | `pending`, `succeeded`, `failed`, `refunded` |
| amount_cents | integer | |
| currency | string default 'usd' | |
| stripe_checkout_session_id | string null unique | |
| stripe_payment_intent_id | string null | |
| check_number / check_received_on | string null / date null | check path |
| recorded_by | fk users null | admin who marked the check |
| timestamps | | |

### stripe_webhook_events — idempotency ledger

| column | type |
|---|---|
| id, stripe_event_id (unique), type, payload (json), processed_at, timestamps |

### event_interests (R2.7 — "notify me when registration opens")

| column | type |
|---|---|
| id, event_id fk, email, organization_name null, notified_at null, timestamps; unique (event_id, email) |

### sponsors / sponsor_staff

`sponsors`: id, name, website, logo_path null, sort_order, timestamps.
`sponsor_staff`: id, sponsor_id fk, name, title, sort_order, timestamps.

### faq_items

id, question (string), answer (text/markdown), attachment_path, attachment_name, sort_order,
is_published, timestamps.

**The attachment is generic, not a `w9_path`** (added 2026-08-19). The signed W-9 is the document
that exists today — doc 11's owner queue had promised "Admin → FAQ (and a file to upload)" while the
FAQ screen had no upload at all — but a floor plan, a parking map or a conduct policy are the same
shape, and a column named after one document has to be joined by another the first time a second
appears.

Three things about how it is stored, each a decision (doc 10, D-9-c):

- **The private disk (`local`), not `public`.** The download goes through `site.faq.download` rather
  than a `Storage::url()`, so unpublishing a question actually withdraws its file. A public-disk URL
  keeps serving for ever, and a signed W-9 carries the fair's EIN and an authorised signature.
- **`attachment_name` remembers the uploaded filename**, because the stored name is randomised and
  somebody filing a W-9 into an accounts-payable system needs `coast-to-coast-w9.pdf` back, not a
  hash.
- **Replacing or clearing an attachment deletes the old file.** Nothing else references that path, so
  nothing else would ever delete it — the same reasoning, and the same shape of method, as
  `Sponsors\Edit::deleteStoredLogo()`.

### ~~content_blocks~~ → laravel-core Content module

Editable page copy (R3.5) uses `core_contents` rows of type `block` (keys like `home.hero`, `about.body`),
managed in core's admin resource, with revisions for free. No app table.

### ~~contact_submissions~~ → laravel-core contact module

R1.7 uses `core_contact_submissions` + core's contact component/routes/receipt/alert. No app table. Leave
`core.contact.user.auto_create` **off** (it creates accounts from an unauthenticated form — see core doc 08).

### messages / message_recipients (R3.6 campaigns — see doc 07)

`messages`: id, event_id fk null (reference event for audience resolution), subject, email_body null,
sms_body null, channels (json), **audience** (string enum, doc 07 §2), **audience_filters** (json),
scheduled_for null, sent_at null, created_by fk users, timestamps.

`message_recipients`: id (ulid), message_id fk, **registration_id fk null** (lapsed/interest recipients have
none), **user_id fk null**, **organization_id fk null**, organization_name/email/phone snapshots, email_status, sms_status,
**email_log_id null** (→ `core_email_logs.id`, linked by the EmailLogged listener — doc 07 §4), error null,
timestamps.

## Enums (`app/Enums`)

Built by card 1.2. All are backed by strings and implement Filament's `HasLabel` / `HasColor`
contracts (doc 02 convention 4), so tables and infolists render them without a mapping array.

```php
MembershipStatus:   Pending | Active | Retired
RegistrationStatus: PendingPayment | Confirmed | Cancelled | Refunded
PaymentMethod:      Stripe | Check
PaymentStatus:      Pending | Succeeded | Failed | Refunded
GrantStatus:        Pending | Approved | Denied | Revoked | Withdrawn
GrantBenefit:       Free | CustomPrice | PercentOff
MessageChannel:     Email | Sms
Audience:           ThisEventConfirmed | ThisEventPendingCheck | ThisEventAll | LastEvent
                    | LapsedLastEvent | AnyPreviousEvent | LapsedAnyPrevious | InterestList
DeliveryStatus:     Pending | Sending | Sent | Failed | Skipped
```

Several carry a behaviour method so a rule lives in exactly one place rather than being re-derived
by every caller:

| Method | What it decides |
|---|---|
| `MembershipStatus::actsForOrganization()` | whether this rep may register, apply for a grant, or edit the org profile |
| `RegistrationStatus::occupying()` / `occupiesASeat()` | the set that blocks a duplicate, counts against `capacity`, and pins a grant as used |
| `GrantStatus::discountsPrice()` | only `Approved` ever changes what an organization pays |
| `GrantStatus::blockingReapplication()` | every status except `Withdrawn` — a denial is final for that fair |
| `Audience::lapsed()` / `isEmailOnly()` | which cases subtract this event's registrants; which resolve to bare addresses |
| `DeliveryStatus::fromEmailLog()` | translates laravel-core's `sending\|sent\|failed`, degrading unknown values to `Pending` rather than throwing |

`DeliveryStatus` was added beyond the doc's original list: `message_recipients.email_status` and
`sms_status` needed a vocabulary, and matching core's three EmailLog values (plus `Skipped`, for a
recipient we deliberately did not text) lets the derived accessor read local column and log row as
one type.

## Factories & seeders

- Factory for **every** model (tests depend on them — project instruction on unit testing).

**As built (card 1.3, 2026-08-18).** Seven seeder classes, each idempotent by a natural key so
re-running never overwrites edited copy:

| Seeder | What it writes | Runs in |
|---|---|---|
| `RoleSeeder` | syncs permissions, creates `coordinator` holding all of them | both |
| `CoordinatorSeeder` | the coordinator account from `config/fair.php` | both |
| `ContentBlockSeeder` | 9 core `block` rows — home, about, roster intros, sponsors, contact, refund policy, check instructions | both |
| `SponsorSeeder` | the 4 sponsor schools + Meg Conner | both |
| `FaqSeeder` | 11 questions from doc 00 | both |
| `EventSeeder` | the fair calendar: 2022–2026 published and past, 2027 unpublished | both |
| `FairFixtureSeeder` | organizations, reps, three years of registrations, grants in every status, the awkward cases | **dev only** |

`FairFixtureSeeder` names its organizations after real colleges but **claims
nothing else about them** — no website, inbox, phone or address (doc 19). The
factory invents all four, and on a real institution's name that is not
placeholder data but wrong data, which then blocks the researched value because
the real-data seeders only fill columns that are empty. Use
`FairFixtureSeeder::organization()` rather than `Organization::factory()->named()`
for anything new there.

Two more joined them on 2026-09-01, when the owner's roster export finally arrived (doc 18). They
are real history rather than fixtures, and they run **last** — `FairFixtureSeeder` does nothing at
all if any organization exists, so seeding the history first would silently cost the fixtures.

| Seeder | What it writes | Runs in |
|---|---|---|
| `OrganizationSeeder` | 156 organizations from `storage/app/private/participants.json` | **dev only**, plus by hand on a real host |
| `RegistrationSeeder` | their 353 places at the 2023–2026 fairs | **dev only**, plus by hand on a real host |
| `AdmissionsOfficeSeeder` | the admissions office behind every one of them — office, page, address, phone, inbox (doc 19) | **dev only**, plus by hand on a real host |

That export is real contact data and is **not in the repository** — `storage/app/private` is
gitignored, so the first two are the only seeders that can find nothing to do. `DatabaseSeeder`
checks first and warns rather than dying, so a developer without the file still gets the fixtures;
running either seeder by name without it throws, because there the roster is what was asked for.
`AdmissionsOfficeSeeder` has no such problem: its own data file is institutional, not personal, and
is committed.

`ProductionSeeder` deliberately does not call either: its contract is that it invents nothing and is
safe on every deploy, and loading a roster is a deliberate one-off. Both share
`ParticipantExportSeeder`, which decides which submitted spellings are one organization and which of
several submissions for a fair wins.

### The fair calendar

`EventSeeder` writes six fairs: **five past ones (2022–2026) and the next (2027)**. The five past
fairs exist so there is somewhere to import history into — `fair:import-roster` (doc 11) resolves
each CSV row by `event_slug` and skips any row naming a fair that is not in the database, so seeding
the back catalogue is a precondition of the import rather than decoration. The depth also matters to
the cross-year audiences: every win-back list is a set difference over the fair history (doc 07 §2),
and its reach is however far back the fairs go.

**Only the 2026 fair's date and price are confirmed** (Tuesday 21 April 2026, $215, from the live
site). The venue is confirmed throughout. The 2022–2025 dates and prices are plausible
reconstructions — the fourth-ish Tuesday of April, with the fee stepping down into the past.

That is tolerable rather than sloppy: **nothing downstream reads a past fair's `price_cents`**. A
registration snapshots what it actually paid into `registrations.price_cents`, and the import CSV
carries a per-row `price_cents` for exactly this reason. A past fair's list price is a record, not an
input, and it is editable in the admin panel.

2027 is seeded **unpublished** — an unpublished event can never take money, so a placeholder date and
price cannot quietly charge an organization the wrong fee. `FairFixtureSeeder` publishes and opens it in
development only, because a workable current fair beats a faithful one locally.

### Two fairs in one year

**Supported today; no schema or code change needed.** Nothing in the application groups fairs by
year. Every "which fair" question is answered by ordering on `starts_at` — `Event::active()`, the
`previousPublished()` scope behind the Last Year roster, and every cross-year campaign audience. A
second fair in a year is one more row.

The only thing a year buys is the naming convention, which is why `EventSeeder` writes each slug and
name out per fair instead of deriving them from a year: a derived `college-fair-{year}` would collide
on the unique index the day a year held two. While the fair is annual `college-fair-2026` reads best;
the day it is not, the pair become `college-fair-spring-2030` and `college-fair-fall-2030`.
**Existing slugs must not be renamed to match** — they are public URLs and the import CSV's join key.

Three tests in `tests/Unit/Models/EventTest.php` pin the behaviour: which fair is active either side
of a spring fair that has a fall fair behind it, that "previous" means the previous *fair* rather
than the previous year, and that two fairs coexist in one calendar year.

One cosmetic loose end if that day comes: the public page is routed at `/last-year` and labelled
"Last year". The logic behind it is `previousPublished()`, which is already "the previous fair" and
stays correct — only the wording would read oddly. Renaming the route would break a public URL, so
it is a deliberate decision rather than a tidy-up.

`DatabaseSeeder` (dev) calls all ten; `ProductionSeeder` calls the first six. Note that
`DatabaseSeeder` does **not** use `WithoutModelEvents` — `Organization` derives `normalized_name`
and `Event` fills a blank slug in `saving` hooks, so muting model events would seed rows the
application itself could never produce, and the duplicate-detection fixtures would seed as
non-duplicates.

Three things about the fixture set are load-bearing rather than decorative, and card 6.3's audience
tests depend on all three: two *past* published fairs (`LastEvent` and `AnyPreviousEvent` are
indistinguishable with one year of history), organizations that lapsed after each of them, and two
organizations with no active rep — one with an `admissions_email` (generic fallback) and one without (the
recipient that gets dropped with a log).

The 2027 event seeds **unpublished** with `TODO-OWNER` in its name because its date and price are
placeholders; `FairFixtureSeeder` publishes and opens it in development only. See
[10-implementation-decisions.md](10-implementation-decisions.md) D-1.3-a…e.

Historical roster import is card 6.6. The export it was waiting for arrived on 2026-09-01 and is
seeded rather than imported — [18-participant-export.md](18-participant-export.md).

## Data lifecycle rules

- Registrations are never hard-deleted once payment exists; cancel/refund instead (audit trail, N1).
- Roster (R1.3) = confirmed registrations with `show_on_roster = true`, ordered by organization name, displaying `logo_path` when set (initial-letter placeholder otherwise).
- Grants are never hard-deleted (audit); revoke/deny instead. A used grant (referenced by a non-cancelled registration) is immutable except admin notes.
- Last Year (R1.4) = same query against the most recent *past* published event.
- Contact submissions (core's `core:prune-contact-submissions`), email logs (`core:prune-email-logs`), and message recipient rows are pruned on schedule per the 24-month privacy promise (N3) — see doc 07 §4 and card 7.1.
