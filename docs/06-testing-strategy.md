# 06 — Testing Strategy

> **Purpose of this document:** How this project is tested. Project instruction: **unit tests are required for
> functions, features, models, and services** — no card in doc 05 is done without its tests.

## Framework & ground rules

- **Pest 5** (`pestphp/pest-plugin-laravel` is installed). Write Pest-style tests, not PHPUnit classes.
- `RefreshDatabase` on feature tests; SQLite in-memory via `phpunit.xml`.
- **Never hit real services:** `Mail::fake()` / `Notification::fake()` / `Queue::fake()` where relevant; bind fake `PaymentGateway` and `NullSms` (doc 04 seams). No network in tests. Exception: EmailLog-integration tests use the `array` transport (not `Mail::fake()`) so laravel-core's capture listeners actually run.
- **laravel-core is tested in its own repo** — don't re-test package internals here; test our configuration and integration points (permissions we register, the EmailLogged listener, the layout override, contact/content wiring).
- **Boot-time flags** (`core.email_log.enabled`): `config()->set()` mid-test does nothing once listeners are wired — set the config in the test's environment setup before app boot (laravel-core doc 00 §5).
- Factories for every model (doc 03); prefer factory states (`Event::factory()->registrationOpen()`, `Registration::factory()->pendingCheck()`) over inline attribute soup.
- Filament/Livewire surfaces are tested with `livewire(SomeResourcePage::class)` helpers; public pages with plain HTTP tests (`get('/faq')->assertSee(...)`).
- Run `php artisan test` and `vendor/bin/pint` before declaring any task complete.

## Directory layout

```
tests/
  Unit/
    Models/         one file per model (casts, helpers, scopes, relationships)
    Services/       RegistrationServiceTest, GrantServiceTest, StripeCheckoutServiceTest, RosterServiceTest, AudienceBuilderTest, SmsServiceTest
    Enums/          label/color mappings if implemented
  Feature/
    Admin/          one file per Filament resource + dashboard widgets + custom actions
    Portal/         auth/access, dashboard, profile, RegistrationWizardTest, receipts
    Public/         one file per public page + contact + interest form
    Webhooks/       StripeWebhookTest
    Notifications/  comms matrix coverage, broadcast job, segmentation
```

## The critical test inventory

The suite must at minimum prove these behaviors (IDs reference docs 01/05):

**Money & payment integrity (N1 — most important tests in the app)**
1. Checkout session amount always equals the registration's `price_cents` snapshot = `Event::priceFor(org)` (grant-aware); client input can never alter it (4.1). `priceFor()` truth table: no grant / free / custom price / percent-off rounding / pending-denied-revoked grants ignored.
1a. Free (100% grant) registration confirms immediately: no payment row, never reaches the gateway, receipt + admin alert queued (2.3).
2. Webhook signature required — unsigned/mis-signed posts → 400, no state change (4.3).
3. Webhook idempotent — same `stripe_event_id` twice → single confirmation, single receipt (4.3).
4. `checkout.session.completed` → registration confirmed + payment succeeded + receipt & admin alerts queued.
5. Amount mismatch → NOT confirmed, admin flagged.
6. Refund → statuses transition, roster hides organization.

**Organizations, membership & grants (D8–D10)**
7. Duplicate non-cancelled registration for same organization+event is rejected (2.3).
8. Normalized-name match at org creation surfaces a warning, doesn't block (R2.7); admin merge repoints users/registrations/grants.
9. Membership gates: `pending` and `retired` reps cannot register, apply for grants, or edit the org profile; claim approval flips pending → active (+ email); self-retire and coordinator-retire both revoke org rights, keep history.
10. Grant lifecycle: one non-withdrawn application per org+event; approve requires a benefit choice; deny requires a reason; revoke blocked once a non-cancelled registration uses it; decision emails queued.

**Registration rules (R2)**
11. Registration blocked when window closed, event unpublished, capacity reached, or acting rep not active.
12. Check path: pending_payment + instructions email w/ PDF (grant-aware amount) queued; admin mark-received → confirmed + receipt.

**Access control**
13. Non-admin cannot reach `/admin` or invoke any admin action (test actions directly, not just navigation).
14. Rep sees only their own organization's registrations/receipts/grants; other orgs' 403.
15. Unverified email cannot use the portal.

**Comms (R4, doc 07)**
16. Each trigger sends exactly its matrix row; SMS only to opted-in users; admin alert respects toggle.
17. **AudienceBuilder truth table** (doc 07 §2): every enum case — orgs qualify, active reps receive; lapsed = last-event minus this-event; cancelled registrations never qualify; retired/pending reps excluded; admissions_email fallback when no active reps (skip + log when empty); dedupe by user then email; resolution at send time.
18. Campaign flow end-to-end with `core.email_log` enabled: send → `message_recipients` frozen → `EmailLogged` listener links `email_log_id` (concurrent sends don't cross wires; missing header no-ops; listener failure never blocks mail) → email_status derives from the log. Scheduled sends dispatch via scheduler test-travel.
19. Themed layout: app mailables render through it (header, footer, campaign CAN-SPAM line); `core::emails.layout` override themes package mail.
20. Interest announcement goes only to un-notified rows and stamps them (6.5).

**Public correctness**
21. Roster = confirmed + `show_on_roster` only; Last Year = previous published event (R1.3/R1.4); logo rendered when set, initial-letter placeholder when not.
22. Event page CTA states: open → register; closed → interest form; not yet open → date notice.
23. Contact form (via laravel-core): consent required on our page, submission stored in `core_contact_submissions`, organizer email queued. (Honeypot/throttle internals are the package's tests — we test our configuration of them.)

**Model unit coverage**
24. `Event::isRegistrationOpen()` truth table; `isFull()`; `priceFor()` (see item 1); money casts; enum casts; phone E.164 normalization; membership scopes.

## Patterns

**Webhook fixture with a valid signature:**
```php
function stripeSignedPost(array $payload): TestResponse {
    $json = json_encode($payload);
    $ts = time();
    $sig = hash_hmac('sha256', "{$ts}.{$json}", config('services.stripe.webhook_secret'));
    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$ts},v1={$sig}",
        'CONTENT_TYPE' => 'application/json',
    ], $json);
}
```

**Filament resource action:**
```php
livewire(ViewRegistration::class, ['record' => $registration->id])
    ->callAction('markCheckReceived', ['check_number' => '1042', 'received_on' => '2027-03-01']);
expect($registration->refresh()->status)->toBe(RegistrationStatus::Confirmed);
Notification::assertSentTo($registration->user, PaymentReceipt::class);
```

**Time-dependent behavior:** always `travelTo()` — never rely on real clock for window/schedule tests.

## CI (when a remote repo exists)

GitHub Actions: PHP 8.3, `composer install`, `npm ci && npm run build` (only if theme assets needed for tests),
`vendor/bin/pint --test`, `php artisan test`. Add later as part of card 7.2/7.3.
