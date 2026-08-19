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

### D-3.0-a — Signup asymmetry: creating a school activates, claiming one waits

**Decision.** `OrganizationService::createWithFounder()` makes the founder `active` immediately;
`claim()` makes them `pending`. Doc 01 D9 specifies this, and it is worth restating why the two
differ: anyone can say they represent Vanderbilt, and on the other side of that claim sit the
school's registration history, its grants and its place on the roster. There is nobody to vouch for
a school only the founder knows about, so making *them* wait would mean waiting on nothing. Both
paths alert the coordinator; the create path carries the duplicate warning the rep pressed past.

### D-3.0-b — A denied claim detaches the account rather than deleting or freezing it

**Decision.** `denyClaim()` sets `organization_id` and `membership_status` to null. The person
survives with a working account and no school. The realistic denial is a typo — somebody claimed
"Boston University" meaning "Boston College" — and a lingering `pending` membership would block them
from claiming the right one.

### D-3.1-a — Portal authorization lives on the rep resources, not in the policies

**Context.** `RegistrationPolicy` and `GrantPolicy` refuse a rep, correctly: they answer "may this
coordinator administer every row".

**Decision.** The rep resources override `canViewAny()` / `canView()` and ask a different question:
"is this my school's row". Loosening the policies to accommodate the portal would have made one
predicate answer two questions and eventually get one of them wrong. Row-level scoping is enforced
twice over — `getEloquentQuery()` filters to the rep's `organization_id`, so another school's
registration is a **404 rather than a 403**, which does not confirm that the row exists.

A user with no school gets `whereRaw('1 = 0')`, never `where('organization_id', null)`.

### D-3.1-b — The portal lists the school's registrations, not the rep's

**Decision.** Scoped by `organization_id`, not `user_id`. A new admissions officer inheriting the
job should see their school's history rather than an empty page and the impression that nothing was
ever done. This is D8 (the organization is the unit that registers) made visible.

### D-3.1-c — Phone numbers are normalised, not rejected

**Decision.** `App\Support\Phone::normalize()` turns `(423) 757-2845` into `+14237572845` on save.
Twilio accepts nothing else, and a rep is not going to type E.164. Validation only refuses input
that cannot be turned into something dialable, so the difference between "we texted them" and "we
had their number and the format was wrong" never arises. A number that already carries `+` is
trusted as-is, so an international rep is not mangled.

Storing a number is **not** consent to use it: `sms_opt_in` is a separate toggle, off by default (N3).

### D-3.2-a — The wizard displays the price and has no field for it

**Decision.** Step three renders `Event::priceFor()` — the same call that writes the snapshot and
the same figure Stripe is handed — and there is no price input anywhere in the form, nor an argument
for one in `RegistrationService::create()`. "The client set the price" is unrepresentable rather
than checked for (N1). When a grant applies, the summary names the list price, the benefit and the
result, because a discount nobody explains is a discount somebody queries.

A fully-granted registration hides the payment step entirely and confirms on submit.

### D-3.2-b — The duplicate check runs at step one as well as in the service

**Decision.** A `rule()` on the fair selector calls `RegistrationService::alreadyRegistered()`. The
service refuses it anyway, so this is not the guard — it is the difference between being told at the
first question and being told after filling in the whole wizard.

### D-3.5-a — Applying is a modal action, not a page

**Decision.** Doc 01 Appendix A says "one screen, no wizard", and the form is one textarea. The
apply action lives on the fee-assistance list as a header action, and the resource has no create
page. All copy — the intro, the helper text, the confirmation toast, and the three status
sentences — is Appendix A verbatim.

The action hides itself when there is no fair left to apply for, and for pending and retired reps.
The fair list is not limited to fairs with registration open, per D-2.6-a.

### D-3.3-a — The receipt renders from the snapshot and only for confirmed registrations

**Decision.** `ReceiptPdf` reads `registrations.price_cents` and the grant that was applied; it
recomputes nothing. A receipt that recalculated would quietly disagree with the invoice the moment
the fair's price changed, which is the one thing a receipt must never do. The download is hidden
until the registration is confirmed — a receipt for money that has not arrived is exactly the
document a finance office files and forgets about.

The Blade template is table-based with inline styles because dompdf supports no flexbox or grid.
It is not an exception to the Filament-only UI directive: a PDF has no Filament to render it.

### D-3.4-a — The interest form is honeypot + IP throttle, and dedupes case-insensitively

**Decision.** `StoreEventInterestRequest` carries a `prohibited` honeypot field named `website`
(plausible enough that a naive bot fills it, invisible to a human), and the route is throttled to
five per hour per IP because it is an unauthenticated write with no captcha. The error message for a
tripped honeypot is deliberately vague, so a bot cannot learn which field caught it.

Addresses are lowercased before `updateOrCreate`, so somebody who signs up as `Dana@` and then
`dana@` is not both told they are on the list and mailed twice. A second submission still improves
what we know — it fills in the school name if the first left it blank.

### D-4.1-a — The gateway takes a registration, never an amount

**Decision.** `PaymentGateway::createSession(Registration)` reads `price_cents` off the record and
there is no amount parameter anywhere in the interface. Combined with the wizard having no price
field (D-3.2-a), the figure Stripe charges, the figure the rep was shown and the figure on the
receipt are the same number by construction rather than by agreement (N1). It also refuses outright
when `price_cents` is zero — a grant made that registration free, so reaching the gateway means a
caller skipped the free branch, and charging $0 would paper over the bug.

### D-4.1-b — Checkout is opened after the registration is saved, from the redirect

**Decision.** `RegistrationService` does not call the gateway. `CreateRegistration::getRedirectUrl()`
does, once the row exists. The session needs the registration id, and more importantly a Stripe
outage must leave a recoverable `pending_payment` row rather than losing the whole registration —
if the session cannot be opened the rep keeps their place and gets the retry button, and the
notification says so.

The retry path (`pay` action on the detail page) matters more than the happy one: a closed tab, a
declined card, an outage mid-signup.

### D-4.2-a — Recording a check confirms in the same transaction

**Decision.** `CheckPaymentService::markReceived()` writes the payment and calls
`RegistrationService::confirmPayment()` inside one `DB::transaction`. A check marked received on a
registration that stayed `pending_payment` is the failure mode that gets a school turned away at the
door. It calls the *same* `confirmPayment()` the Stripe webhook does, so both paths fire the same
events and produce the same receipt.

The amount defaults to the snapshot, so the common case is two fields and a button. A different
figure is recorded as-is — this is a ledger of what happened, not of what should have — and a short
check produces a persistent warning rather than a refusal. Nobody should be turned away over a
dollar; the alternative to a warning is noticing in April.

### D-4.3-a — The `charge.refunded` webhook owns the refund transition, not the admin action

**Decision.** `StripeCheckoutService::refund()` calls Stripe and stops. It does not mark the payment
refunded. The webhook handler does, which means a refund issued from our panel and one issued from
the Stripe dashboard leave the database in exactly the same state — the only way the two can be
trusted to agree.

A **partial** refund moves the payment but not the registration: the school is still coming, it just
paid less. A full refund sets `Refunded` and clears `show_on_roster`, because a refunded
registration is one that is not attending.

The refund action is offered only on a settled *card* payment. A mailed check is refunded by writing
one back, which this application cannot do, and a button that pretended otherwise would be worse
than none.

### D-4.3-b — Idempotency is claimed before any work, and a handler failure returns 200

**Decision.** `StripeWebhookHandler::handle()` claims the `stripe_webhook_events` row first and
returns early if the event has been seen. Everything downstream is therefore safe against Stripe's
redelivery, and `confirmPayment()` is independently idempotent as well.

The controller catches handler exceptions, reports them, and still answers 200. A 500 makes Stripe
retry for three days, so a deterministic bug would be retried thousands of times; the ledger records
that the event was seen and the exception is in the log for a human.

**A missing webhook secret is a 500, not a bypass.** Without it every caller is "Stripe" and anyone
who can reach the URL can confirm a registration.

### D-4.3-c — An amount mismatch flags and refuses to confirm

**Decision.** When `amount_total` differs from the registration's snapshot, the handler appends a
`PAYMENT MISMATCH` note, fails the payment row, logs an error and leaves the registration
`pending_payment`. The only routes here are a tampered session or a bug in our own pricing, and both
mean the figure the school agreed to and the figure that moved are different. Confirming would bless
it.

### D-4.3-d — Webhook routes live in their own file

**Decision.** `routes/webhooks.php`, loaded through `withRouting(then:)` on the `api` middleware
group, so there is no session and no CSRF token. The caller is a server and its proof of identity is
the signature. A separate file makes that exemption visible rather than burying it in a middleware
exclusion list.

### D-5.1-a — The public site is a third Filament panel — **answered 2026-08-19: rework it**

**Context.** The owner's directive at build time (2026-08-16) was that all UI is Filament: no
hand-built Blade, Tailwind, Livewire or Flowbite. Doc 02 offered two readings for public pages —
"Filament custom pages exposed publicly" or "route views rendered with Filament's Blade components".

**Decision as built.** The stricter reading: `App\Providers\Filament\SitePanelProvider`, a panel at
the site root with no `->login()` and no `Authenticate` middleware. Every page is a Filament `Page`
whose `content()` returns a schema. There is no hand-written markup anywhere in the public site, and
the public pages inherit the same palette as `/admin` and `/portal`.

**Why it was flagged.** A Filament panel is an application shell, and a public marketing site
rendered in one is unusual. `->topNavigation()` and a wide content area get it close to reading like
a website rather than an admin screen, but the visual design is the owner's call, and this was the
piece of the build most likely to want revisiting. The alternative — Blade views — was noted as a
contained change: the pages' logic would move to controllers almost unaltered.

**Owner's answer (2026-08-19).** Rework it. The standing directive is now that **frontend UI is
Blade + Livewire + Flowbite and backend UI is Filament**, workspace-wide. The public site is
therefore built with the wrong tool and diverges from the documented direction.

**What the rework is.** Roughly the contained change this entry predicted:

- Retire `SitePanelProvider` and the eight `Page` classes under `app/Filament/Site/Pages/`
  (Home, About, Representatives, LastYear, Sponsors, Faq, Contact, EventPage).
- Move each page's `content()` logic into a controller action or a Livewire full-page component —
  the roster pages (`RosterTable`, D-5.3-a) and event listings are the natural Livewire candidates,
  the rest are plain Blade.
- Add the public Blade layout and wire Flowbite: `flowbite` in `package.json`,
  `@plugin 'flowbite/plugin'` in `resources/css/app.css`, `import 'flowbite'` in
  `resources/js/app.js`. `ckbs` is the reference wiring in this workspace.
- `RendersContentBlocks` (D-5.2-a) and the laravel-core Content/Contact modules are unaffected —
  they are data sources, not UI.
- The public-page tests are already plain HTTP tests (`get('/faq')->assertSee(...)`, doc 06), so
  most of them should survive the move; they assert content, not Filament internals.

**Not yet scheduled.** No roadmap card exists for this. `bootstrap/providers.php` still registers
`SitePanelProvider` last so `/admin` and `/portal` claim their prefixes first; that ordering comment
goes away with the panel.

**The rep portal is a separate, still-open question.** `/portal` is also a Filament panel, and reps
are external users rather than staff. The sibling `duespay` project made the opposite call for the
same shape of surface — "owner portal, *not* a Filament panel; plain Livewire pages; owners get
consumer UI, not admin software". Whether that reading extends here is the owner's to make, and it
is a far larger rework than the public site (the registration wizard is a Filament form wizard).
Nothing has been changed on that assumption. **Ask before acting.**

Registration order matters and is commented in `bootstrap/providers.php`: `SitePanelProvider` is
last, so `/admin` and `/portal` register their literal prefixes first. Filament adds no catch-all,
so there is no conflict either way.

### D-5.2-a — A missing content block renders as nothing

**Decision.** `RendersContentBlocks::block()` returns null when the block is absent or empty, and
`blocks()` drops the nulls. Not a placeholder and not an error: a half-seeded database, or a block
somebody archived, should leave a page one paragraph short rather than printing `content.missing` in
front of a hundred colleges.

### D-5.3-a — One roster widget, two pages

**Decision.** `RosterTable` is abstract; `CurrentRoster` and `PreviousRoster` differ only in which
event they name. The staleness bug doc 00 recorded — the live site's Last Year page showing the
*current* roster — is exactly what happens when they are two pieces of code. `PreviousRoster` reads
`RosterService::previousEvent()`, which reads the `previousPublished()` scope, which is also what the
cross-year campaign audiences will use.

### D-5.3-b — The roster renders with the page, not after it

**Decision.** `protected static bool $isLazy = false`. Filament lazy-loads widgets by default, which
is right in an admin panel and wrong here: the roster *is* the page, and its job is to be read — by a
search engine, by a rep checking whether their school is already listed, and by anyone whose
JavaScript did not run. A list that only exists after a round-trip is invisible to all three.

### D-5.3-c — The logo placeholder is a generated inline SVG

**Decision.** A school with no logo gets a data-URI SVG carrying its initial (R1.3). Generated rather
than fetched from an avatar service: a third-party image would leak every visitor's request off-site
and break the page when that service is down, for a letter in a circle. Images are lazy-loaded and
carry the school name as alt text.

### D-5.4-a — The contact consent is a stated notice, not an unvalidated checkbox — **flagged**

**Context.** Doc 02 says the contact page adds a consent checkbox "on our side", with laravel-core
providing the honeypot, throttle, storage and receipt.

**Decision.** The privacy notice is stated plainly above the embedded `<x-core::contact-form />`,
and there is no checkbox. Core's controller validates only its own fields, so a checkbox added here
would be unvalidated — a control that can be skipped is theatre rather than consent, and worse than
saying the thing plainly. Making it real means a change in the `laravel-core` repo, and the workspace
rule is explicit that this app must not edit a sibling project.

**Owner decision:** if you want a hard checkbox, it is a small addition to core's contact controller
and form (an optional, host-configurable required field), and then two lines here.

### D-5.4-b — The interest capture exists twice, deliberately

**Decision.** The event page offers it as a Livewire form; `POST /events/{event}/interest` remains as
a plain route. The Livewire form is what a visitor uses; the route is the non-JavaScript path and the
only place an IP throttle can hang. Both lowercase the address before `updateOrCreate` and both
refuse a filled honeypot, so they cannot diverge in the ways that matter.

`EventPage::$event` is `#[Locked]`: Livewire re-hydrates a model property from its key on every
request, and without it a visitor could edit the payload to sign up against a different fair's list.
Small harm, free to close.

### D-5.4-c — An unpublished fair is a 404

**Decision.** `EventPage::mount()` aborts 404, not 403, on an unpublished event. A 403 confirms the
draft exists; the next fair's date leaking before the coordinator announces it is a real, if minor,
disclosure.

### D-6.0-a — Email components sit flat beside the layout, not in a subdirectory

**Context.** Doc 07 §1 names `emails/components/button.blade.php`. The theme is registered as an
anonymous component path with an `emails` prefix, so the layout is `<x-emails::layout>`.

**Decision.** The components live at `emails/panel.blade.php`, `emails/button.blade.php`,
`emails/roster-line.blade.php` — referenced as `<x-emails::panel>`. A prefixed anonymous path
resolves `<x-emails::layout>` but **not** a nested `<x-emails::components.panel>`: Blade leaves the
latter uncompiled and prints the raw tag into the email. That is exactly as visible as it sounds and
exactly as easy to miss until somebody reads a receipt — it survived a first test run because the
assertions were on the layout, not the panel.

### D-6.0-b — Component row arrays are built in `@php`, never in the tag attribute

**Decision.** Every view that passes a `:rows` array assembles it in a `@php` block first. Blade
parses component tags with a regex over the raw attribute text, so a double quote inside a PHP
expression — `$a."\n".$b` — closes the attribute early and the whole tag is left uncompiled. The
same class of bug as D-6.0-a and just as silent. Called out in the views themselves so the next
person does not reintroduce it.

### D-6.0-c — Package mail is themed by overriding core's layout view

**Decision.** `resources/views/vendor/core/components/mail/contact/layout.blade.php` is a four-line
shim that delegates to `<x-emails::layout>`. Laravel resolves the app's `vendor/core/…` copy before
the package's own, so it needs no registration. Doc 07 §1's "one theme, two entry points" — a
contact receipt and a registration receipt look like the same organization sent them.

### D-6.1-a — One `AdminAlert` class, not five

**Decision.** Every coordinator alert has the same shape — something happened, here are the facts,
here is where to look — so there is one class taking a headline, a rows array, a link and an
optional SMS body. Five classes differing by a subject line would be five places to keep in step.

SMS is opt-in per alert: a new registration is good news that can wait for morning, and passes
`smsBody: null`; money arriving passes one. `AdminAlerts::send()` is the single place that answers
"who is the coordinator", falls back to `mail.from.address` rather than losing an alert, and honours
the `fair.alerts.enabled` switch — the one to flip for a bulk import or a holiday.

### D-6.1-b — Consent lives on the notifiable, not at the call site

**Decision.** `User::routeNotificationForSms()` returns null unless `sms_opt_in` is true **and**
there is a number. `SmsChannel` asks Laravel's own routing API, so no notification can text somebody
by forgetting to check (N3, D4). Adding a channel to a message is permission to try, never a promise
that a given recipient receives one.

### D-6.1-c — Registration mail goes to the fair contact, not the account holder

**Decision.** The listeners mail `registrations.rep_email`. They are usually the same person and
sometimes deliberately not — the wizard asks who is staffing the table precisely so a registration
made by a director for a colleague reaches the colleague.

Grant decisions go to **every active rep**, not only the applicant: the applicant may have left by
the time a decision lands, and a grant nobody knows about is a discount nobody claims.

### D-6.3-a — `AudienceBuilder` returns DTOs, and dedupes on account before address

**Decision.** `RecipientDto` rather than models or arrays, because a recipient is sometimes a user,
sometimes a school's `admissions_email` with nobody behind it, and sometimes a bare address off the
interest list. `dedupeKey()` prefers the account id and falls back to a lowercased address, so a rep
active across three past years qualifies three times and is mailed once.

The `generic` flag is surfaced in the composer's preview, so a coordinator can see how much of a
send is going to nobody in particular. A school with neither an active rep nor an admissions email
is dropped **and logged** — doc 07's "no silent caps": a school vanishing from a win-back list
without a trace is how it stops being invited.

### D-6.4-a — `sent_at` is stamped before the fan-out

**Decision.** `SendEventBroadcast` marks the message sent, then loops. If the process dies halfway
through a hundred notifications, a retry that re-resolved the audience and re-sent would be far
worse than one that stops. The job also no-ops entirely on an already-sent message, so a queue
retrying after a timeout cannot mail a hundred schools twice.

A sent campaign is then immutable: no edit, no delete, both in the policy and in the resource. It is
the record of what a hundred schools were told, and the delivery table beneath it only means
anything if the message has not changed since.

### D-6.4-b — The test send uses a throwaway recipient row

**Decision.** "Send a test to me" builds an unsaved `MessageRecipient` with a generated ULID rather
than persisting one. The email is identical, header and all, but rehearsals do not pollute the real
delivery table.

### D-6.5-a — The announcement stamps each row as it sends

**Decision.** Not a bulk update afterwards. If the loop dies halfway, the people already mailed are
marked and a re-run picks up where it stopped. Combined with sending only to `unnotified()`, this
makes the button safe to press twice — which matters, because the realistic mistake is a coordinator
unsure whether the first press worked.

### D-6.6-a — The import bypasses `RegistrationService` deliberately

**Decision.** `fair:import-roster` writes through the model. The service's rules are about *taking* a
registration — the window is open, the rep is active, the price comes from the current grant — and
none of them apply to recording something that happened in 2025.

It matches schools on the **normalized name**, so "The Ohio State University" in a fifteen-year-old
export lands on the "Ohio State University" already in the directory. It fills gaps in an existing
profile but never overwrites one. It refuses to invent a fair from a spreadsheet cell — an event with
no date, venue or price would leave the roster and the audiences to cope. And it is idempotent by
(event, organization), so the owner fixes a column and runs it again.

Only `organization_name` and `event_slug` are required. Everything else is optional because a
fifteen-year-old export will be missing things, and a partial record of a school that attended is
worth far more than no record. Every lookup uses `?? null` for the same reason — a CSV with just the
two required columns must not take the import down on row one.

### D-6.x-a — The test suite needs a 512M memory limit

**Context.** `phpunit.xml` now sets `memory_limit` to 512M.

**Decision.** dompdf is memory-hungry and the suite renders a receipt or a check form in a couple of
dozen tests. Each render is comfortably within the 128M default alone; a whole suite in one process
is not. Raised rather than trimming the PDF assertions, which are worth having.

**Worth watching in production:** a long-lived queue worker rendering many PDFs may want the same
treatment. Noted for the deployment runbook (card 7.3).

### D-7.1-a — No Content-Security-Policy, deliberately

**Decision.** `SecurityHeaders` sets HSTS (over HTTPS only), `X-Content-Type-Options`,
`X-Frame-Options` and `Referrer-Policy`, and no CSP.

This application takes card payments through a *hosted* Stripe page — no card fields on our origin —
and runs three Filament panels, which ship inline styles and Alpine expressions. A CSP tight enough
to be worth having would break the admin panel; one loose enough not to (`unsafe-inline`,
`unsafe-eval`) would be decoration. That is a judgement, not an oversight, and it is the sort of
thing a later security review should revisit deliberately rather than "add" in ten minutes.

HSTS is withheld on plaintext requests, which also keeps it out of local development: pinning
`coasttocoastcollegefair.test` to https in a developer's browser for a year is a genuinely annoying
thing to do to somebody.

### D-7.1-b — The Stripe ledger prunes processed rows only, after 90 days

**Decision.** Stripe stops retrying after roughly three days, so a row older than that has no
idempotency job left — but the ledger is also the answer to "did Stripe ever tell us about this?",
which comes up weeks later from somebody reconciling a bank statement. Ninety days. A row with no
`processed_at` is a delivery that failed halfway and is never pruned.

### D-7.2-a — An HTTP route smoke instead of a browser pass

**Context.** Card 7.2 asks for a browser smoke of both panels and the wizard.

**Decision.** `tests/Feature/SmokeTest.php` discovers GET routes from the router and loads each one
as the right user. No browser driver, so it runs in CI on a machine with no Chrome, and — because
routes are discovered rather than listed — a page added later is smoked without anybody remembering
to add it.

**It earned its place immediately.** It found `/admin/messages/create` returning 500 on
`Select::descriptions()`, which is a `Radio` method in Filament v5. No resource test had caught it,
because none of them opened the create page. That is exactly the class of bug a browser pass is for,
and this caught it for a fraction of the cost.

What it does not replace: anything about how a page *looks*, and any JavaScript behaviour. A real
browser pass before launch is still worth an hour of somebody's time (added to the go-live checklist
in doc 11).

### D-7.3-a — The deployment runbook is doc 11, not doc 07

**Context.** Card 7.3 says "write `docs/07-deployment.md`". Doc 07 was already the email design when
the card was written.

**Decision.** [11-deployment.md](11-deployment.md). Doc numbers are load-bearing — code comments
cite them (workspace `CLAUDE.md`) — so renumbering an existing file to free 07 would have been the
worse trade. The note is at the top of the new file too.
