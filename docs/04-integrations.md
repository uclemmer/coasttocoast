# 04 — Integrations: Stripe, Postmark, Twilio, Filament, laravel-core

> **Purpose of this document:** Design and rules for every external integration. Each section states the
> package, config, flow, failure handling, and the test seam. Implementers: do not call any vendor SDK outside
> the service classes named here.

## Stripe (payments)

**Package:** `stripe/stripe-php`. **Approach:** Stripe **Checkout** (hosted payment page) — no card data on our
servers, minimal PCI scope (SAQ A). Do not build custom card forms with Elements in v1.

### Service: `App\Services\Payments\StripeCheckoutService`

```
createSession(Registration $r): CheckoutSessionDto
  - amount from $r->event->price_cents (never from request input)  [N1]
  - metadata: registration_id
  - success_url: /portal (registration detail); cancel_url: wizard payment step
  - stores stripe_checkout_session_id on a pending Payment row
refund(Payment $p, ?int $amountCents = null): void
```

### Webhook: `POST /webhooks/stripe` (routes/webhooks.php, CSRF-exempt)

1. Verify signature with `STRIPE_WEBHOOK_SECRET`; reject 400 on failure.
2. Insert into `stripe_webhook_events` keyed by `stripe_event_id`; if already present, return 200 immediately (idempotency).
3. Handle:
   - `checkout.session.completed` → `RegistrationService::confirmPayment()` → registration `confirmed`, payment `succeeded`, queue receipt + admin alerts.
   - `checkout.session.expired` → payment `failed`; registration stays `pending_payment` so the rep can retry.
   - `charge.refunded` → payment `refunded`; registration `refunded` if full refund.
4. Always 200 after recording; do heavy work in queued jobs so Stripe doesn't retry into timeouts.

### Failure handling & rules

- Rep returns from Stripe before the webhook lands → portal shows "processing payment" state; never trust the redirect alone.
- Amount mismatch in webhook payload vs. payment row → log error, flag registration `notes`, notify admin; do not auto-confirm.
- Dev: Stripe CLI forwarding (see 02). Test cards: `4242 4242 4242 4242`.

**Test seam:** interface `PaymentGateway` implemented by `StripeCheckoutService`; tests bind a fake. Webhook tests post signed fixture payloads (generate signature with the test secret) — see 06.

## Postmark (transactional + broadcast email)

**Package:** `symfony/postmark-mailer` (Laravel's native `postmark` transport). Config: `POSTMARK_TOKEN`,
`MAIL_MAILER=postmark` in production, `log` locally.

### Message streams

- `outbound` (default) — receipts, verification, password reset, check instructions, admin alerts.
- `broadcast` — reminder/announcement campaigns (R3.6, R2.7). Set per-mailable via headers so list sends can't hurt transactional deliverability.

### Mailables/notifications (queued, one class per R4 row)

`RegistrationCheckInstructions` (attaches dompdf PDF), `PaymentReceipt`, `AdminNewRegistration`,
`AdminPaymentReceived`, `EventReminder`, `RegistrationOpenAnnouncement`, plus Filament/Laravel built-ins for
verification and password reset (Rep panel).

### Rules

- All sends queued (N7). From address: `contact@coasttocoastcollegefair.com` — requires Postmark sender-domain verification (DKIM + return-path) before launch; flagged in 01 open questions.
- **Every email renders in the themed HTML layout** (doc 07 §1) — app mail via `emails/layout.blade.php`, laravel-core mail via the overridden `core::emails.layout`.
- **Every send is logged** by laravel-core's EmailLog module; campaign recipients link to their log rows via the `EmailLogged` listener (doc 07 §4). Delivery outcomes on `message_recipients` derive from the linked log. Optional v1.1: Postmark webhooks for bounces/opens.
- Never send real email in tests: `Mail::fake()` / `Notification::fake()` always (except EmailLog-integration tests, which use the `array`/`log` transport so the capture listeners run — doc 07 §6).

## Twilio (SMS)

**Package:** `twilio/sdk`. **Scope (decision D4):** rep event reminders + admin alerts only. No SMS confirmations.

### Service seam

```
interface SmsService { send(string $toE164, string $body): SmsResult; }
TwilioSms   — real implementation (config: TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM)
NullSms     — local/dev + default test binding; logs instead of sending
```

Delivered through a custom notification channel (`SmsChannel`) so notifications can declare
`via(): ['mail', SmsChannel::class]`.

### Rules

- Send only to `users.sms_opt_in = true` (reps) or `ADMIN_ALERT_PHONE` (admin alerts). Opt-in checkbox lives in the wizard and portal profile; STOP/opt-out handling is delegated to Twilio Messaging Service defaults.
- Phone numbers normalized to E.164 on save (validation rule + cast).
- Keep bodies ≤ 320 chars; include fair name; no links in admin alerts beyond the portal URL.
- A2P 10DLC registration is required for US SMS from local numbers — an ops task before launch (add to 01 open questions with owner).
- Failures: log, mark `message_recipients.sms_status = failed`, never block the email path.

## laravel-core (foundation package)

**Package:** `uclemmer/laravel-core` — the owner's package (sibling repo `C:\Users\uriah\Herd\laravel-core`;
composer path repository in dev). Requires **PHP 8.4** + Filament v5. Modules used: **admin panel shell**
(prebuilt panel + `core.admin.plugins` seam for our `FairPlugin`), **roles/permissions** (`HasCoreRoles`,
`core:sync-permissions`, app permissions via `core.permission_providers`), **EmailLog** (send tracking — doc 07),
**contact** (R1.7), **content** (page copy blocks), optionally **queue metrics** and **profiles**.

### Rules

- **Read the package's own docs** (`laravel-core/docs/`) before touching a module; its conventions ("Notes for AI agents" sections) are binding there.
- Module flags: `core.email_log.enabled` and `core.queue.*` are **READ AT BOOT** — config changes need worker restarts; `core:doctor` catches drift. Run `core:doctor` in CI/deploy.
- Don't recreate anything the package provides (tables, resources, permissions). If a package change is needed, it happens in the laravel-core repo with its own tests/docs — never patched from this app.
- Integration checklist lives in doc 02.

## ~~Filament (UI framework)~~ → Blade, Livewire and `uclemmer/laravel-ui`

**Filament is gone from this application.** It is not a dependency, no `app/Filament/` directory
exists, and as of 2026-08-19 neither does any of its wiring — see doc 02. The rebuild happened in
two steps: the public site left in Phase 8 (docs/10, D-5.1-a), and the staff and portal surfaces
followed with the `/staff` rebuild (docs/13) and the core `0.4` upgrade (docs/14).

The rules below outlived the framework, because none of them were really about it:

- **Authorization on every action via Policies — never rely on navigation hiding alone.** This got
  *more* important, not less. Filament re-evaluated an action's `visible()` closure before running
  it, so a hidden action was an uncallable one. Livewire couples nothing: a public method on a
  mounted component is reachable by anyone who reached the component.
- **Actions call the services in doc 02** (Mark check received, Refund, Resend confirmation). Keep
  the logic out of the UI class, whichever framework the UI class belongs to.
- **Access:** staff screens through `ActsForStaff`, the portal through `ActsForAnOrganization` — both
  ported from the panels' equivalents, and both refusing in `mount()` so the page is unreachable
  rather than merely unauthorised.
- **Testing:** Pest with the `livewire()` helper, exactly as before — the helper outlived the panels
  because it was always testing Livewire underneath.

## Integration environment matrix

| Environment | Stripe | Mail | SMS |
|---|---|---|---|
| Local (Herd) | Test keys + Stripe CLI webhook forwarding | `log` mailer | `NullSms` |
| Tests (Pest) | Faked `PaymentGateway`; signed fixture payloads for webhook route | `Mail::fake()` | `NullSms` / mocked interface |
| Staging | Test keys + registered webhook | Postmark (test stream or sandbox token) | Twilio test creds or real number w/ team-only recipients |
| Production | Live keys, live webhook secret | Postmark verified domain, outbound+broadcast streams | Twilio live number (A2P registered) |
