# 10 — Implementation Decisions Log

> **Purpose of this document:** Every judgement call an implementing session made without the owner
> in the room, in one place, so they can be reviewed and reversed cheaply. Doc 05 records *what* each
> card shipped; this file records *why a choice was made where the plan did not say*.
>
> Started 2026-08-18 by the session that built cards 1.2 onward overnight. The owner authorised the
> session to "take your best options and record them"; four standing answers were given before the
> session started and are recorded as A1–A4 below.

## Standing answers given before the build (2026-08-18)

| # | Question | Answer |
|---|---|---|
| A1 | May the session install `stripe/stripe-php`, `twilio/sdk`, `barryvdh/laravel-dompdf` (card 1.4)? | **Yes, all three.** |
| A2 | How far to build? | **Sequentially from card 1.2, as far as the session gets**, committing per card. |
| A3 | Is there a real ISPEUS export for card 6.6? | **No.** Build an idempotent importer against a documented CSV schema; the owner runs it when the file exists. |
| A4 | How to handle doc 01's unanswered open questions? | **Use the documented defaults and flag `TODO-OWNER`.** 2027 date/price as placeholders, confirmed-only roster, refund copy as an editable content block. |

## Decisions

### D-1.2-a — `DeliveryStatus` enum added beyond doc 03's list

**Context.** Doc 03 named three enums to add (`MembershipStatus`, `GrantStatus`, `GrantBenefit`) plus
the three already listed, and doc 02 mentioned `MessageChannel`. Nothing named a type for
`message_recipients.email_status` / `sms_status`, which doc 07 §4 says must read as one value with
laravel-core's `EmailLog.status`.

**Decision.** Added `App\Enums\DeliveryStatus` with core's three values (`sending|sent|failed`) plus
`Pending` (queued, nothing attempted) and `Skipped` (deliberately not contacted — no phone, or not
opted in). `DeliveryStatus::fromEmailLog()` translates core's enum and degrades an unrecognised
value to `Pending` instead of throwing, so a delivery table still renders if the package adds a
status we have not seen.

**Reversible?** Yes, cheaply — it is a cast on two columns.

### D-1.2-b — Capacity counts awaiting-payment registrations, not confirmed alone

**Context.** Doc 03 says `capacity` is an "optional cap on confirmed registrations".

**Decision.** `Event::isFull()` counts *occupying* registrations: confirmed **plus** pending payment.
Counting confirmed alone lets a run of mailed checks oversell the room, and every oversell is a
school that has to be turned away after it has already been told it has a place. The same set backs
the duplicate check and the grant-used check, so there is one definition
(`RegistrationStatus::occupying()`).

**Owner check:** if the fair would rather oversell than hold seats for unpaid checks, this is the
one line to change.

### D-1.2-c — `Event::priceFor()` falls back to list price on a malformed grant

**Context.** Doc 03 defines the three benefit branches but not what happens to an `approved` grant
whose `benefit_type` is null.

**Decision.** Charge list price. An approved grant with no recorded benefit is a data fault, and the
alternatives are worse: charging zero gives away a registration on the strength of a bug, and
throwing takes the wizard down for a school that did nothing wrong. Covered by a test.

### D-1.2-d — Percentage discounts round **down**

**Context.** Doc 03 says "percent_off → rounded down" without saying which way the half cent goes.

**Decision.** `floor()`, so 33% off $215.01 charges $144.05. The fraction of a cent goes to the
school. Pinned by a test with a rate that does not divide evenly.

### D-1.2-e — Duplicate and one-grant-per-event rules are service-level, not unique indexes

**Context.** Both rules are "one live row per pair", where "live" excludes cancelled / withdrawn.

**Decision.** No partial unique indexes — SQLite, MySQL and Postgres do not express them portably,
and doc 02 requires portable migrations. Composite non-unique indexes back the checks;
`RegistrationService` and `GrantService` enforce them. Recorded here because the schema alone looks
under-constrained, and it is not an oversight.

### D-1.2-f — `Organization::normalizeName()` strips a leading "the" but never "University"/"College"

**Context.** R2.7 wants a soft duplicate warning at org creation.

**Decision.** Lowercase, strip punctuation, collapse whitespace, drop a leading `the `. Deliberately
does **not** strip institution words: "Boston University" and "Boston College" are different
schools, and a merge prompt that conflates them is worse than a missed warning. The model derives
the column on save, so no caller can forget it.

### D-1.2-g — `message_recipients.email_log_id` has no foreign key

**Context.** It points at `core_email_logs.id`.

**Decision.** Column only, indexed, no constraint. `core:prune-email-logs` deletes those rows on a
schedule; a constraint would either block the prune or cascade campaign history away with it. The
accessor treats a missing log as "fall back to the local column", which is the same code path an
environment with logging disabled takes.

### D-1.2-h — The Unit suite gets the app and a database, but not the permission sync

**Context.** `tests/Pest.php` bound `TestCase` + `RefreshDatabase` + `core:sync-permissions` to
`Feature` only, so `tests/Unit/Models/*` had no application at all.

**Decision.** Extended `TestCase` + `RefreshDatabase` to `Unit` as well, without the permission
sync. Model tests need real tables for casts, scopes and relationships; none of them touch a Gate,
so paying for the sync on each would buy nothing. A unit test that genuinely needs permissions is a
feature test in the wrong directory.
