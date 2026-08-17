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

id, question (string), answer (text/markdown), sort_order, is_published, timestamps.

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

```php
RegistrationStatus: PendingPayment | Confirmed | Cancelled | Refunded
PaymentMethod:      Stripe | Check
PaymentStatus:      Pending | Succeeded | Failed | Refunded
```

## Factories & seeders

- Factory for **every** model (tests depend on them — project instruction on unit testing).
- `DatabaseSeeder` (dev): coordinator user (`admin@example.com`, seeded `coordinator` role), the 2025 and 2026 events (past, with organizations + confirmed registrations so Last Year AND cross-year audiences are testable — doc 07 §2), a 2027 event (registration open, placeholder date/price flagged `TODO-OWNER`), 4 sponsors with staff, FAQ items and core content blocks seeded from the current site's copy (see 00), a few pending-check registrations, lapsed organizations (2025/2026 only), orgs with multiple/retired/pending reps, and grants in each status (incl. an approved free and a percent-off applied to registrations).
- Production seeder: sponsors, FAQ, content blocks, coordinator role/user only. Historical roster import is card 6.6.

## Data lifecycle rules

- Registrations are never hard-deleted once payment exists; cancel/refund instead (audit trail, N1).
- Roster (R1.3) = confirmed registrations with `show_on_roster = true`, ordered by organization name, displaying `logo_path` when set (initial-letter placeholder otherwise).
- Grants are never hard-deleted (audit); revoke/deny instead. A used grant (referenced by a non-cancelled registration) is immutable except admin notes.
- Last Year (R1.4) = same query against the most recent *past* published event.
- Contact submissions (core's `core:prune-contact-submissions`), email logs (`core:prune-email-logs`), and message recipient rows are pruned on schedule per the 24-month privacy promise (N3) — see doc 07 §4 and card 7.1.
