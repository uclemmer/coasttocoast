# 11 — Deployment & Operations Runbook

> **Purpose of this document:** everything between a green test suite and a fair that takes money.
> Written 2026-08-19 with card 7.3.
>
> **Numbering note.** Doc 05 card 7.3 asks for `docs/07-deployment.md`. That number was taken by
> [07-email-design.md](07-email-design.md) before the card was written, and doc numbers are
> load-bearing — code comments cite them (workspace `CLAUDE.md`). This is 11 instead.

## What this application needs from a host

| Requirement | Why |
|---|---|
| PHP **8.4** or later | `uclemmer/laravel-core` requires `^8.4`; the app pins the same |
| HTTPS, with a valid certificate | Stripe will not send webhooks to plaintext, and the HSTS header only goes out over TLS |
| MySQL or PostgreSQL | SQLite is the development database. Migrations are portable — no driver-specific SQL |
| A **queue worker** | Every email and text is queued. Without a worker, nothing is ever sent and nothing errors |
| A **one-minute cron** running `schedule:run` | Scheduled campaigns fire from it, as does all pruning |
| Outbound HTTPS to `api.stripe.com`, `api.postmarkapp.com`, `api.twilio.com` | |
| Read access to the private `uclemmer/laravel-core` repo | A deploy key or PAT — see [09-package-wiring.md](09-package-wiring.md) |

**Deploy target is still undecided** (owner, 2026-08-16). Nothing below assumes Forge, Ploi or
Laravel Cloud; all of it is ordinary Laravel.

## First deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate            # only if APP_KEY is not already set
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\ProductionSeeder" --force
php artisan storage:link            # organization and sponsor logos are on the public disk
npm ci && npm run build
php artisan filament:optimize
php artisan config:cache route:cache view:cache
php artisan core:doctor             # must exit 0 — see below
```

`ProductionSeeder` is idempotent and safe to re-run on every deploy. It creates the coordinator
account **without a usable password** outside local — send a reset link rather than looking for one
(doc 10, D-1.3-a).

### Every deploy after the first

```bash
php artisan down --render="errors::503" --retry=60 --with-secret
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan filament:optimize
php artisan core:doctor
php artisan queue:restart           # workers hold old code and old config until told otherwise
php artisan up
```

**`--render` is the whole point of the maintenance page.** Without it, `artisan down` still shows
`resources/views/errors/503.blade.php` — the exception handler registers `resources/views/errors`
under the `errors::` namespace — but it renders it **live, per request, through a booted
application**, in the window where `vendor/` is being replaced and the config cache is being
rewritten. With `--render`, Laravel renders the page **once, now**, and serves that flat HTML from
`storage/framework/down` for the rest of the outage. That is why the view carries inline styles and
static `/images/` paths and calls no `@vite`: it has to survive its own deploy. See doc 10, D-8.5-c.

`--with-secret` prints a URL that bypasses maintenance mode, so the coordinator can walk the site
before `up` lifts it for everybody. `--retry=60` sets `Retry-After`, which is what keeps a crawler
from treating the outage as a permanent 410.

**A deploy with no migrations does not need the window.** `down`/`up` exist here because
`migrate --force` runs against a schema the old code is still serving; skip them for a
docs-or-assets-only release rather than taking the site down for nothing.

**If a deploy dies midway, the site stays down** — that is the correct behaviour, not a bug. Fix
forward and run `php artisan up` deliberately; a `finally`-style automatic `up` would lift
maintenance on a half-migrated database.

**`queue:restart` is not optional.** `core.email_log.enabled` is read at boot (doc 08), so a worker
started before a config change keeps the old value indefinitely.

**Gate the deploy on `core:doctor`.** It exits non-zero on *jointly* wrong configuration — a contact
form set to create accounts nothing can claim, an email log enabled with no store — which is the
class of problem that looks fine in isolation and breaks in production.

## Environment

Every key is in [`.env.example`](../.env.example) with a comment. The ones that must be right before
the first real registration:

| Key | Notes |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | |
| `APP_URL` | Absolute URLs in email are built from this |
| `STRIPE_KEY`, `STRIPE_SECRET` | **Live** keys, not test |
| `STRIPE_WEBHOOK_SECRET` | From the live endpoint, not the CLI. A missing secret makes the webhook return 500 for every delivery, by design (doc 10, D-4.3-b) |
| `MAIL_MAILER=postmark`, `POSTMARK_TOKEN` | |
| `POSTMARK_MESSAGE_STREAM`, `POSTMARK_BROADCAST_STREAM` | Both streams must exist in Postmark |
| `MAIL_FROM_ADDRESS` | On the verified domain |
| `ADMIN_ALERT_EMAIL` | Where new-registration and payment alerts go. Also the contact-form inbox |
| `ADMIN_ALERT_PHONE` | Optional. Blank means email-only alerts |
| `TWILIO_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM` | **All three or none** — a partial set binds `NullSms` and no text is ever sent (doc 10, D-1.4-a) |
| `COORDINATOR_EMAIL`, `COORDINATOR_NAME` | Who `ProductionSeeder` provisions |
| `FAIR_CONTACT_*` | The block in the public footer, the email footer and the printed check form. The postal address is a CAN-SPAM requirement, not decoration |
| `FAIR_BRAND_COLOR`, `FAIR_BRAND_LOGO_URL` | The logo **must** be an absolute URL served from `public/` — a Vite-hashed path does not resolve in a mail client |

## The queue worker

```
php artisan queue:work --queue=default --tries=3 --max-time=3600
```

Run it under a supervisor that restarts it. Two things worth knowing:

- **Memory.** dompdf renders the receipt and the check form, and it is not frugal. The test suite
  needed `memory_limit=512M` to render a couple of dozen PDFs in one process (doc 10, D-6.x-a).
  A long-lived worker doing the same is worth giving the same headroom, or `--max-jobs=200` so it
  recycles.
- **A failed job is a school not told.** Watch `failed_jobs`. laravel-core's queue module surfaces
  it in the admin panel (`core.queue.enabled` is on).

## Scheduled work

`php artisan schedule:run` every minute. What it drives:

| Command | When | What happens if it never runs |
|---|---|---|
| `fair:send-scheduled-campaigns` | every minute | Scheduled campaigns never go out. Nothing errors — they just sit |
| `core:prune-email-logs` | 03:10 daily | The log grows without bound; stale `sending` rows never become `failed`, so the delivery table lies |
| `core:prune-contact-submissions` | 03:20 daily | Contact submissions outlive the retention promise |
| `fair:prune-message-recipients` | 03:30 daily | Campaign recipients outlive the 24-month promise (N3) |
| `fair:prune-stripe-events` | Mondays 03:40 | The idempotency ledger grows without bound |

## Stripe

1. Live mode → **Developers → Webhooks → Add endpoint**: `https://<host>/webhooks/stripe`.
2. Subscribe to `checkout.session.completed`, `checkout.session.expired`, `charge.refunded`.
3. Copy the signing secret into `STRIPE_WEBHOOK_SECRET`.
4. Send a test event and confirm a 200, then check `stripe_webhook_events` has the row.

**The webhook is the source of truth.** Nothing else in this application confirms a card payment —
not the browser returning from Checkout, not a coordinator. If webhooks stop arriving, registrations
sit at `pending_payment` while the money has already moved, so an alert on Stripe's webhook failure
rate is worth setting up.

## Postmark

1. Verify the sending domain: DKIM **and** the return-path (custom bounce domain). Both.
2. Create the two message streams: `outbound` (transactional) and `broadcast` (campaigns). The
   names must match `POSTMARK_MESSAGE_STREAM` / `POSTMARK_BROADCAST_STREAM`.
3. Send yourself a test from the campaign composer ("Send a test to me") before the first real send.

Keeping the streams apart is what stops a badly received bulk send from damaging the deliverability
of a receipt. Do not point both at `outbound` to save a step.

## Twilio

- A2P 10DLC registration is **required** for US SMS from a local number, and it takes days to
  weeks. Start it early; it is the item most likely to be the long pole.
- Until it is done, leave `TWILIO_SID` blank. The app binds `NullSms`, logs what it would have sent,
  and everything else keeps working.
- SMS is a bonus channel throughout. Nothing fails when it is off.

## Backups

- **Database, nightly, retained 30 days.** It holds every registration, payment record and grant
  decision. `payments` is a financial audit trail; losing it is not recoverable from Stripe alone,
  because the check payments were never in Stripe.
- **`storage/app/public`**, which holds organization and sponsor logos. Less critical — the schools
  can re-upload — but cheap to include.
- **Test a restore before the first fair, not after.**

## Go-live checklist

- [ ] `core:doctor` exits 0 on the production host
- [ ] Queue worker running and supervised; `schedule:run` cron confirmed with `schedule:list`
- [ ] Stripe live webhook registered, secret set, test event returns 200
- [ ] Postmark domain verified (DKIM + return-path); both streams exist
- [ ] A real test registration end to end: card payment → webhook → receipt email arrives
- [ ] A real test registration on the check path: instructions email arrives with the PDF attached
- [ ] Coordinator can sign in at `/admin` (password reset, not a seeded password)
- [ ] **The owner content queue below is empty**
- [ ] DNS cut over from ISPEUS
- [ ] Backups running and a restore tested
- [ ] `php artisan down --render="errors::503"` shows the maintenance page, and `php artisan up` clears it

## Owner content queue — the things only Matt can supply

None of these block a deploy; all of them are visible to a visitor.

| Item | Where it lives | Flagged as |
|---|---|---|
| The real 2027 fair date and price | Admin → Fairs → College Fair 2027 | `TODO-OWNER` in the name; the fair is **unpublished** until this is done |
| Refund and cancellation policy | Admin → Content → `policy.refunds` | `TODO-OWNER` in the title |
| Parking and unloading directions | Admin → FAQ | `TODO-OWNER` badge in the table |
| Hotel list | Admin → FAQ | `TODO-OWNER` badge |
| Fair conduct guidelines | Admin → FAQ | `TODO-OWNER` badge |
| Signed W-9 PDF | Admin → FAQ (and a file to upload) | `TODO-OWNER` badge |
| Google Map embed for the venue | Admin → FAQ | The design's embed is pinned at Chattanooga generally, not the venue — see the asset queue below |
| Brand colour and logo | `FAIR_BRAND_COLOR`, `FAIR_BRAND_LOGO_URL` | Email falls back to the app name in text |
| Historical rosters, 2022–2026 | `php artisan fair:import-roster <file.csv>` | Five past fairs are seeded and waiting; see below |

### Design assets still outstanding (2026-08-19)

The Claude Design handoff in [`docs/design-handoff/`](design-handoff/) names four
assets it could not supply. **The site renders correctly without all four** — each has a deliberate
fallback — but each is a visible gap that only Matt can close.

| Asset | Where it goes | What happens without it |
|---|---|---|
| The four sponsor school logos | Admin → Sponsors → logo | The tile falls back to the school's name set in the display face. Legible, clearly a placeholder |
| A wordmark with a transparent background | `public/images/wordmark.jpg` | The current file is a JPEG with a white background, so it sits on the hero photo as a white card with a rounded corner and a shadow. That is the design's own treatment, so it does not look broken — but a transparent PNG or SVG would let it sit on the photograph directly |
| A higher-resolution cityscape | `public/images/cityscape.jpg` | The current file is fine at desktop widths and soft on a large display. It is the hero background and the maintenance page's full-bleed image |
| A map embed pinned to the venue | Admin → FAQ | The handoff's embed centres on Chattanooga generally rather than on the Convention Center. Pointing it at the building is a one-line change once the correct embed URL exists |

**One discrepancy needs Matt's word rather than a file.** The design gives the venue address as
**1 Carter Plaza**; doc 00 (transcribed from the live site) and the production seed both say
**1150 Carter Street**. The build kept 1150 Carter Street — a wrong address on a page whose job is
getting people to a building is worse than a stale one, and the live site is the better evidence.
This is a content block, so correcting it is an admin-panel edit rather than a deploy. See doc 10,
D-8.5-d.

### The roster import

Cross-year campaign audiences — the win-back lists — are only as good as the history behind them.
Without the import, the first year on this system has no previous year and `LastEvent` /
`LapsedAnyPrevious` resolve to nothing.

Whatever ISPEUS can export needs massaging into these columns:

```
organization_name,website,admissions_email,admissions_phone,
address_line1,address_line2,city,state,postal_code,
rep_name,rep_email,rep_phone,event_slug,price_cents,confirmed_on
```

Only `organization_name` and `event_slug` are required; `event_slug` must match a fair that already
exists. `EventSeeder` seeds five past fairs for this purpose — `college-fair-2022` through
`college-fair-2026` — so there is somewhere to put each year's roster. **A row naming a fair that is
not in the database is skipped with a warning, not created**, so check the summary line rather than
assuming a clean run imported everything. Everything else is optional.

`price_cents` is per row on purpose: what a school actually paid in 2023 is a fact about that
registration, not about the 2023 fair, whose seeded list price is a reconstruction (doc 03). Supply
it where the history knows it.

```bash
php artisan fair:import-roster storage/app/roster-2026.csv --dry-run
php artisan fair:import-roster storage/app/roster-2026.csv
```

It is idempotent: fix a column and run it again.

## Where to look when something is wrong

| Symptom | First thing to check |
|---|---|
| A school paid but is still "awaiting payment" | `stripe_webhook_events` — did the delivery arrive? Then the Stripe dashboard's webhook log |
| No email at all | Is the queue worker running? Then Admin → Email log — a row with status `sending` means the transport never confirmed |
| A campaign shows every recipient as "queued" forever | `core:prune-email-logs` promotes stale rows to `failed`; if the schedule is not running, nothing ever moves |
| A school is missing from a campaign | Does it have an **active** rep? A school with only pending or retired reps falls back to `admissions_email`, and with neither is dropped — that drop is logged |
| Registration says closed when it should be open | Is the fair **published**? An unpublished fair is never open, whatever the window says |
| Filament pages 500 after a deploy | `filament:optimize`, then `view:clear` |
