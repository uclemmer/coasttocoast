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

### D-1.3-a — The coordinator seeder only sets a known password locally

**Decision.** In `local`/`testing`, `CoordinatorSeeder` creates the account with the password
`password`. Anywhere else it sets 64 random characters nobody holds and prints "password unset —
send a reset link". A seeder that plants a guessable admin password on a production host is a back
door with a changelog entry. Identity comes from `config/fair.php` (`COORDINATOR_EMAIL` /
`COORDINATOR_NAME`).

### D-1.3-b — The 2027 event seeds unpublished

**Context.** A4 authorised placeholder date and price for 2027.

**Decision.** `EventSeeder` writes it with `is_published = false` and `TODO-OWNER` in the name.
An unpublished event is never registration-open, so a placeholder the owner forgets about cannot
quietly charge a school the wrong fee — publishing it is a deliberate act once the real figures are
known. `FairFixtureSeeder` publishes and opens it in development only, because a local app with no
current fair is not worth running.

### D-1.3-c — Every seeder is idempotent and never overwrites edited copy

**Decision.** All seeders key on a natural identifier (content slug, sponsor name, FAQ question,
event slug, coordinator email) and use `firstOrCreate`. `FairFixtureSeeder` no-ops entirely if any
organization exists. `ProductionSeeder` is therefore safe on every deploy. A seeder that reset the
coordinator's wording each release would be worse than no seeder.

### D-1.3-d — Missing FAQ content is seeded as visible TODO-OWNER rows

**Context.** The live site's FAQ covers parking, hotels, conduct guidelines and a W-9 download; doc
00 records that those sections exist but not their text.

**Decision.** Seed the question with a `TODO-OWNER: transcribe …` answer rather than inventing
plausible detail. A confidently wrong parking answer sends a hundred representatives to the wrong
door; an obviously unfinished one sends them to the phone. Same treatment for the refund policy,
which doc 01 lists as an open question — it is a content block so it can be fixed without a deploy.

### D-1.3-e — `config/fair.php` created early

**Context.** Doc 07 §1 puts brand tokens in `config/fair.php` as part of card 6.0.

**Decision.** Created it at card 1.3 instead, because the coordinator identity and the contact block
were needed by the seeders, and the contact block is the same data the public footer, the email
footer and the check PDF all need. Card 6.0 fills in the real brand colour and logo URL; the keys
are already there.

### D-1.4-a — The SMS binding requires *complete* credentials

**Decision.** `AppServiceProvider` binds `TwilioSms` only when `sid`, `token` and `from` are all
present; anything less binds `NullSms`. A half-configured Twilio account is the realistic failure —
someone sets the SID and forgets the number — and it must degrade to no SMS rather than to a client
that throws on first use inside a queued notification, where the exception lands nowhere useful.
This also means the whole test suite gets `NullSms` for free: no test can send a real message by
forgetting to fake something.

### D-1.4-b — The Stripe binding is not conditional

**Decision.** `PaymentGateway` always resolves to `StripeCheckoutService`, configured or not. There
is no safe silent fallback for taking money: a `NullPaymentGateway` that "succeeded" would confirm
registrations nobody paid for. A missing secret therefore fails loudly at the point of use, and
payment tests bind `Tests\Fakes\FakePaymentGateway` explicitly.

### D-1.4-c — Unbuilt service methods throw, naming their card

**Decision.** `StripeCheckoutService::createSession()` / `refund()` and
`RegistrationService::cancel()` throw `RuntimeException` messages that name the card that will
implement them, rather than returning a plausible empty value. A half-built payment path must fail
in development, not confirm a registration quietly. A test pins the message.

### D-1.4-d — `RosterService` is implemented, not stubbed

**Context.** Card 1.4 asked for a "shell"; card 5.3 owns the page.

**Decision.** Implemented both query methods now, because they are the model scopes composed and
nothing about them waits on Phase 5. `forPreviousEvent()` reads the same `previousPublished()` scope
the audience builder will, which is the fix for doc 00's recorded bug — the live site's Last Year
page was showing the *current* roster, which is what happens when the two pages are two pieces of
code. Card 5.3 still owns logos and the initial-letter placeholder.

### D-1.4-e — `services.postmark.key` replaced with `token`

**Context.** The Laravel skeleton ships `'postmark' => ['key' => env('POSTMARK_API_KEY')]`, while
doc 02 and `.env.example` use `POSTMARK_TOKEN`.

**Decision.** Replaced the entry rather than adding a second one — two `'postmark'` keys in the same
array is a silent overwrite. `MailManager` reads `services.postmark.token` before
`services.postmark.key`, so `token` is the correct name, and the stream ids sit alongside it.

### D-2.1-a — Dollars/cents conversion lives on the form component, not the page classes

**Context.** The first attempt used a `price_dollars` field marked `dehydrated(false)` plus
`mutateFormDataBeforeCreate()` / `mutateFormDataBeforeSave()` on the two page classes.

**Decision.** Moved to `formatStateUsing()` / `dehydrateStateUsing()` on the `price_cents` component
itself. The original approach did not merely look worse — it silently saved **every fair at zero**,
because a field marked `dehydrated(false)` is stripped from the form data before the mutation hook
ever sees it. Keeping the conversion on the component also means create and edit cannot drift apart:
there is one place, not two. `App\Support\Money` owns the arithmetic, and rounds rather than
truncating — `215.10 * 100` is `21509.999999999996` in IEEE 754, and a cast would charge a school a
cent less than it agreed to, forever.

### D-2.1-b — The slug is suggested on create only

**Decision.** `afterStateUpdated` on the name field sets the slug only when `$operation === 'create'`.
A fair's slug is in its public URL and in whatever emails and links have already gone out; renaming
the fair must not silently break them. Pinned by a test.

### D-2.1-c — A fair with registrations cannot be deleted

**Decision.** `EventPolicy::delete()` returns false once any registration references the event, and
`deleteAny()` is always false. The foreign keys cascade, so deleting a fair would take real financial
history with it. Unpublishing is the reversible equivalent and is what the UI steers toward.

### D-2.1-d — Test infrastructure: `livewire()` and the panel helpers

**Context.** `pestphp/pest-plugin-livewire` has no Pest 5 release, and neither panel in this app is
marked `->default()`.

**Decision.** `tests/Pest.php` defines `livewire()` over `Livewire::test()` — the name doc 06's
examples already use — plus `usingAdminPanel()` / `usingRepPanel()`, which set the current panel the
way the route middleware does at runtime. Marking one panel `->default()` instead would have been a
production change made for a test's convenience, and would have picked a winner between two panels
that are genuinely peers.

### D-2.1-e — `UserFactory::coordinator()` now grants every synced permission

**Context.** The state granted only `admin.access`, matching card 1.1's intent ("guarantees only the
one permission that opens the panel"). `RoleSeeder` grants the full set.

**Decision.** The factory now mirrors `RoleSeeder`. There is one coordinator role and she runs all of
it, so a factory that produced a coordinator holding one permission was modelling a user who does not
exist — and made every admin resource test fail as a 403 indistinguishable from a policy bug. The
feature suite syncs permissions before each test, so the table is populated by the time this reads it;
`admin.access` is still granted by name as a fallback for the unit suite, which does not sync.

### D-2.3-a — Services fire domain events; card 6.1 hangs the mail off them

**Context.** Card 2.3's DoD says a free registration must queue a receipt, and card 2.6's says a
grant decision must queue an email — but the notification classes and the themed layout they render
in are cards 6.0/6.1, several phases later.

**Decision.** `RegistrationService` and `GrantService` send no mail. They fire domain events
(`RegistrationCreated`, `RegistrationConfirmed`, `RegistrationCancelled`, `GrantApplied`,
`GrantApproved`, `GrantDenied`, `GrantRevoked`, `GrantWithdrawn`) and card 6.1 attaches listeners.

This is not only sequencing convenience. It keeps comms out of the services entirely, which is what
lets one `confirmPayment()` serve the Stripe webhook, the check action and the free path without any
of them knowing what gets sent. It also makes the "exactly one receipt" rule testable now, with
`Event::assertDispatchedTimes(..., 1)`, instead of waiting for Phase 6.

**What card 6.1 must do:** register listeners for these events. Nothing else needs to change.

### D-2.3-b — `createManualEntry()` is a separate method, not a nullable actor

**Context.** The coordinator can enter a registration with no acting rep, past capacity, and after
registration has closed.

**Decision.** Two entry points rather than one with a nullable `$rep`. "Skip the membership check" is
something a caller has to ask for by name; it must not be what happens when an argument is null.
The manual path still refuses duplicates and still snapshots the grant-aware price — those two
protect the *data*, whereas the membership and window gates protect the *process*, and only the
latter is the coordinator's to override.

### D-2.3-c — `confirmPayment()` is idempotent

**Decision.** An already-confirmed registration returns unchanged and fires nothing. Stripe
redelivers a webhook until it gets a 2xx, and a second `RegistrationConfirmed` means a second
receipt — which schools notice and the coordinator has to apologise for. Pinned by a test that calls
it twice and asserts one dispatch.

### D-2.3-d — Registration creation runs inside a transaction

**Decision.** The duplicate check, the capacity check and the insert share one `DB::transaction`.
Two reps of the same school pressing register in the same second would otherwise both read "no
existing registration" and both write one, and the resulting pair of invoices is exactly the
situation R2.7 exists to prevent.

### D-2.6-a — Grant applications close when the fair happens, not when registration does

**Context.** Card 2.6 says applications are allowed "only while the event registration is open or
upcoming".

**Decision.** `GrantService::apply()` refuses only once `starts_at` is in the past. A school lining
its funding up *before* registration opens is the normal case and the whole point of applying early,
so gating on the registration window would block the most legitimate applicant.

### D-2.6-b — `approve()` validates the benefit parameters rather than trusting the form

**Decision.** A `custom_price` grant with no price, or a `percent_off` grant with no percentage (or
one outside 1-100), is refused. Left unchecked, `Event::priceFor()` falls through to list price: the
school would be told in writing that it had a grant and then charged in full. The method also clears
the parameters that do not belong to the chosen benefit, so a grant cannot carry contradictory
figures.

### D-2.6-c — Withdrawal is the only status that frees the one-per-fair slot

**Decision.** `GrantStatus::blockingReapplication()` covers pending, approved, denied **and**
revoked. A school that changes its mind may apply again with a better case; a school that was denied
may not reapply for the same fair, because that decision is the coordinator's and reapplying would be
a way around it.

### D-2.2-a — `OrganizationService` created for membership and merge

**Context.** Card 2.2 puts approve/deny/retire and merge-duplicates on the Organizations resource;
card 3.0 owns the membership lifecycle from the portal side.

**Decision.** All of it lives in `App\Services\OrganizationService` now, and both the admin actions
and (later) the portal call it. Filament actions that wrote membership columns directly would be a
second implementation of rules that card 3.0 is about to need anyway, and the two would drift.

### D-2.2-b — Merge repoints first, then deletes, and reports collisions rather than fixing them

**Decision.** `merge()` moves users, registrations and grants onto the survivor **before** deleting
the husk — the foreign keys cascade, so the other order destroys precisely the history the merge
exists to preserve. Profile fields fill gaps in the survivor but never overwrite a value somebody
entered.

It returns the registrations that now collide on a fair instead of resolving them. Which of two
paid registrations a school keeps is a decision about money; the coordinator gets a persistent
warning and decides.

### D-2.2-c — The Registration edit form exposes only roster visibility, notes and the fair contact

**Decision.** Status, price, school and fair are not editable fields. Editing `status` by hand would
skip the events that send receipts; editing `price_cents` would break the snapshot that proves what
a school agreed to pay (N1); moving a registration to another fair would carry a price nobody agreed
for it. Cancelling is an action that goes through the service, and `RegistrationPolicy::delete()`
always returns false.

### D-2.2-d — Manual entry goes through `CreateRegistration::handleRecordCreation()`

**Decision.** The create page calls `RegistrationService::createManualEntry()` rather than letting
Filament write the row. That keeps one set of rules: the coordinator still gets the duplicate check
and the grant-aware price snapshot, and what she skips she skips because the service was asked to,
not because this page found a different route to the database. A refusal is re-thrown as a
`ValidationException` on the field it belongs to, so a duplicate reads as a message about the school.

### D-2.2-e — CSV export is streamed, not queued

**Decision.** Filament's queued exporter mails a file that ignores the table's filters. The feature
the coordinator wants is "filter, press export, get that list", so the action streams
`getFilteredTableQuery()` straight to the browser. Fair sizes are in the hundreds; this is
comfortably within budget, and the alternative would not be the same feature.

### D-2.6-d — The Grants resource has no create or edit page

**Decision.** Only `index` and `view`. A grant is applied for by a school and decided by the
coordinator through three actions. An edit form would let someone set `status = approved` without a
benefit — which `Event::priceFor()` reads as "no discount", so the school would be told in writing it
had a grant and then be charged in full. Routing every change through `GrantService` makes that
unrepresentable rather than merely discouraged. The revoke action hides itself once a grant is used,
because an action that always fails is worse than no action.

### D-2.5-a — Dashboard revenue is summed from price snapshots, not from the payments table

**Decision.** `ActiveFairOverview` sums `registrations.price_cents` over confirmed rows. Summing
`payments.amount_cents` would omit every free registration — a grant has no payment row — and so
would quietly report a grant-heavy year as a bad one. "Awaiting payment" is the pending set, which
is money in the post rather than money lost.

### D-2.5-b — The widgets show the active fair only

**Decision.** Both widgets scope to `Event::active()`, and `RecentRegistrations` shows an empty table
rather than every registration ever taken when no fair is published. A dashboard that leads with last
year's tail tells the coordinator nothing about today.
