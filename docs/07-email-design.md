# 07 — Email Design: Theme, Audiences & Tracking

> **Purpose of this document:** The email system in depth — the themed HTML template every message uses,
> the coordinator's audience-targeted campaigns (including cross-year segments), and send tracking via the
> owner's **`uclemmer/laravel-core`** package. Added 2026-08-16 at the owner's direction; supersedes the
> thinner "broadcast" sketch that docs 03/05 carried before this date.
>
> **Prerequisite reading for implementers:** the laravel-core package's own docs at
> `C:\Users\uriah\Herd\laravel-core\docs\` — especially `00-architecture.md` (conventions),
> `03-email-logging.md` (the EmailLog module this design builds on), and `08-contact.md`.

## The laravel-core package (what we're building on)

`uclemmer/laravel-core` (namespace `UClemmer\LaravelCore`) is the owner's reusable foundation package:
Filament v5 panel plugin, in-house roles/permissions, email logging, queue metrics, content management,
profiles/settings, contact form, and a `core:doctor` diagnostic. Facts that matter here:

- Requires **PHP ^8.4** and Laravel `^13`. (It required `filament/filament ^5.0` when this was
  written; core dropped that in `0.4` and narrowed to Laravel 13 in `0.3`.)
- **EmailLog module** (`core.email_log.*` config, `core_email_logs` table): listeners on `MessageSending`/
  `MessageSent` capture **every** outgoing email — envelope, subject, HTML/text bodies, headers, attachment
  metadata — with status `sending|sent|failed`, correlated via an `X-Core-Email-Log-Id` header. Ships a
  read-only Filament resource (sandboxed HTML preview), a **Resend** action, and a `core:prune-email-logs`
  command that also promotes stale `sending` rows to `failed`. Fires `UClemmer\LaravelCore\Events\EmailLogged`
  after each row is created. `core.email_log.enabled` is **READ AT BOOT** — restart queue workers after
  toggling.
- This is **send tracking**: did we send it, to whom, what did it contain, did the transport accept it.
  It does not track opens/clicks. If the coordinator later wants open rates, Postmark's open/click tracking
  + webhooks is the extension point — design for it (nullable columns), don't build it in v1.

## 1. Themed HTML email template

One branded layout, used by **every** email the app sends — transactional and campaign alike — so a receipt
and a reminder look like the same organization.

### Design

- `resources/views/emails/layout.blade.php` — a classic email-safe HTML shell: table-based, 600px max width,
  fully inlined styles (no `<style>` reliance beyond resets), fair wordmark header on the brand color, white
  content card, footer with the fair's contact block (doc 00), venue/date line for the active event, and a
  slot-based body (`{{ $slot }}`). Components: `emails/components/button.blade.php`,
  `emails/components/panel.blade.php` (details box used by receipts), `emails/components/roster-line.blade.php`.
- Every app Mailable/Notification renders through this layout. **No** Laravel default markdown theme.
- **Package mail is themed too:** laravel-core renders its mail (contact receipts/alerts) through its
  `core::` view namespace — override the published `core::emails.layout` view so package email inherits the
  same shell. One theme, two entry points.
- Campaign emails (section 2) add a footer line explaining why the recipient got the email ("You're receiving
  this because your institution registered for a Coast to Coast College Fair") — good practice and a CAN-SPAM
  requirement for anything promotional, alongside the fair's physical mailing address (already in the footer).
- Test with a snapshot-ish Pest test (key strings + structure, not byte-exact HTML) plus a manual render
  route in local (`/dev/mail-preview/{mailable}`, local-only) so the coordinator can eyeball the theme.

### Brand tokens

Pull colors/logo from the current site during Phase 5 theming; keep them in `config/fair.php`
(`fair.brand.color_primary`, `fair.brand.logo_url`, `fair.contact.*`) so email and Filament theme share one
source. Absolute URLs only in email (no Vite assets); logo served from `public/`.

## 2. Audiences — who the coordinator can email

The owner's requirement: email different groups — reps registered in previous years, previous-year reps who
have **not** registered this year, last year's reps, this year's reps, etc. Because these groups span events
and reps come and go (D8/R2.10), **audiences qualify at the organization level and deliver to people**:
an organization qualifies through its registration history; the recipients are that organization's **active**
reps. Retired and pending reps are never emailed by campaigns; an org with no active reps falls back to its
`admissions_email` (one recipient, flagged `generic`) so a school with rep turnover doesn't silently vanish
from the win-back list.

### `AudienceBuilder` service (`App\Services\AudienceBuilder`)

Input: an `Audience` definition (backed enum + optional filters). Output: deduplicated list of
`RecipientDto { user_id?, organization_id, registration_id?, organization_name, name, email, phone?, sms_opt_in, generic: bool }`.

| Audience enum case | Qualifying organizations (relative to a "reference event", default = active event) |
|---|---|
| `ThisEventConfirmed` | orgs with a confirmed registration on the reference event |
| `ThisEventPendingCheck` | orgs pending_payment + method=check on the reference event |
| `ThisEventAll` | confirmed + pending on the reference event |
| `LastEvent` | orgs with any non-cancelled registration on the previous published event ("last year's schools") |
| `LapsedLastEvent` | in `LastEvent` **minus** anyone in `ThisEventAll` ("last year but not registered this year") |
| `AnyPreviousEvent` | orgs with a non-cancelled registration on **any** past event ("registered in previous years") |
| `LapsedAnyPrevious` | `AnyPreviousEvent` minus `ThisEventAll` (the win-back list) |
| `InterestList` | `event_interests` rows for the reference event (email-only recipients, no org needed) |

Filters composable on top: `smsOptedInOnly`, `paymentMethod`, `excludeEmails` (manual suppression),
`skipGenericFallback` (active reps only, no admissions_email rows).

**Resolution rules (write tests for each):**

1. **Qualify orgs, deliver to people:** each qualifying org contributes its **active** reps; `pending` and
   `retired` reps are always excluded (R2.10). No active reps → one `generic` recipient at the org's
   `admissions_email` (skipped entirely if that's empty too — log the drop, doc 07 "no silent caps").
2. **Dedupe by user, then by email** (case-insensitive) — a rep active across three past years appears once.
3. **Freshest contact info wins:** rep name/email/phone come from the user record; the org's
   `admissions_email` fallback uses the org profile.
4. Cancelled/refunded registrations never qualify an org for an audience (they didn't attend).
5. "Previous"/"last" resolve against **published** events ordered by `starts_at` — same definition the
   Last Year page uses (doc 03), one source of truth: reuse the `previousPublished()` scope.
6. An audience is resolved **at send time**, not at compose time — the coordinator schedules a message to
   "lapsed schools" and whoever is lapsed when it fires is who gets it. The resolved list is then frozen into
   `message_recipients` for the audit trail (with `organization_id` so results group by school).

### Historical data caveat

Cross-year audiences are only as good as the data: the first year on the new system has no prior-year
registrations. **Card 6.6 (doc 05) imports the 2025/2026 rosters + rep contact emails** from the current
site/ISPEUS export as `organizations` (+ admissions contact info where known) + manual registrations on
seeded past events, so `LastEvent` and `AnyPreviousEvent` work from day one. Owner to supply the export
(added to doc 01 open questions).

## 3. Campaign composer (coordinator UX)

Filament resource on the Admin panel (replaces the thinner "Message resource" sketch in doc 05's old card 6.3):

1. **Compose:** subject, email body (rich text/markdown rendered into the themed layout), optional SMS body
   (only sent to sms-opted-in recipients when enabled — decision D4 scope still holds: reminders/logistics).
2. **Audience:** pick an enum case + filters; live **preview count** and expandable recipient list before
   sending (Filament action modal). Show per-audience definitions in helper text so "lapsed" is unambiguous.
3. **Send:** now, or scheduled (`scheduled_for`); `SendEventBroadcast` job resolves the audience, creates
   `message_recipients` rows, fans out queued per-recipient notifications.
4. **Review:** message detail page shows delivery table — recipient, email status (live from the linked
   `core_email_logs` row: sending/sent/failed), SMS status, resend-single action (delegates to laravel-core's
   resend), and totals widget.

Test email action ("send this to me") is required — coordinators always want it.

## 4. Tracking — wiring `message_recipients` to laravel-core's EmailLog

Enable `core.email_log.enabled = true` (with `store_body = true`, `prune_after_days` ≥ 400 so a full fair
cycle stays reviewable; revisit for storage). Then:

1. Campaign mailable stamps `X-CTC-Recipient-Id: {message_recipient ulid}` on the outgoing message
   (same header trick the package itself uses).
2. App listener on `UClemmer\LaravelCore\Events\EmailLogged` reads that header from `$log->headers` and sets
   `message_recipients.email_log_id`. Listener must be exception-safe (never block mail) — mirror the
   package's own listener discipline.
3. `message_recipients.email_status` becomes a **derived accessor** from the linked EmailLog's status
   (`sending|sent|failed`), falling back to the local column for rows with no log (SMS-only, or logging
   disabled in a given environment). Stale `sending` promotion comes free from `core:prune-email-logs`.
4. Transactional email (receipts, check instructions, verification) needs no app wiring — the package logs
   every send globally, and the coordinator can browse/search/resend from the EmailLog resource already.
5. Schedule both prune commands in `routes/console.php`: `core:prune-email-logs` daily (the package does not
   self-schedule) alongside our own pruning (doc 03 lifecycle rules).

**Privacy note (N3):** enabling body storage means every receipt and campaign body is retained in
`core_email_logs`. That's the point (audit + resend), but it joins the data covered by the 24-month pruning
promise — hence `prune_after_days` config rather than never-prune.

## 5. Schema deltas (doc 03 is updated to match)

- `messages`: replace free-form `segment` json with `audience` (string enum) + `audience_filters` (json);
  keep `channels`, `scheduled_for`, `sent_at`.
- `message_recipients`: `registration_id` now **nullable** (lapsed/interest recipients have no current
  registration); add `user_id` (nullable fk), `organization_id` (nullable fk — groups results by school),
  `email_log_id` (nullable, ULID, references `core_email_logs`), keep name/email/phone snapshots and
  status columns.
- Dropped from our schema (laravel-core provides them): `contact_submissions` → `core_contact_submissions`,
  `content_blocks` → core Content module (`core_contents`, type `block`). `users.is_admin` → core
  roles/permissions. Doc 02/03 carry the details.

## 6. Test inventory additions (doc 06 updated to match)

- AudienceBuilder truth table per enum case, incl. dedupe, lapsed-set subtraction, cancelled exclusion,
  org-level qualification with active-rep delivery, retired/pending reps excluded, admissions_email fallback
  (and its skip-when-empty logging), at-send-time resolution.
- Themed layout renders for a representative mailable (header, footer, CAN-SPAM line on campaigns);
  `core::emails.layout` override applies to package mail.
- EmailLogged listener links the right recipient row (concurrent sends don't cross wires); missing header
  no-ops; listener failure doesn't block mail.
- Campaign flow end-to-end with `core.email_log` enabled in the test app: send → recipients created →
  log rows linked → statuses derived. (Boot-time flag: set config **before** app boot in the test — see
  laravel-core doc 00 §5 on why mid-test `config()->set` silently does nothing.)
