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

### D-5.1-a — The public site is a third Filament panel — **closed 2026-08-19: reworked**

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

**Done — Phase 8, 2026-08-19.** All five bullets above were carried out, and the prediction held:
the page logic moved across almost unaltered and the public HTTP tests survived the move. The panel,
the eight `Page` classes, the three roster widgets and the ordering comment in
`bootstrap/providers.php` are all gone. A test now asserts the negative — the landing page carries no
`class="fi"` and loads no `/css/filament/` stylesheet — so the panel cannot come back unnoticed.
Cards 8.0–8.5 in doc 05 record what each step actually did.

**The rep portal is a separate, still-open question.** `/portal` is also a Filament panel, and reps
are external users rather than staff. The sibling `duespay` project made the opposite call for the
same shape of surface — "owner portal, *not* a Filament panel; plain Livewire pages; owners get
consumer UI, not admin software". Whether that reading extends here is the owner's to make, and it
is a far larger rework than the public site (the registration wizard is a Filament form wizard).
Nothing has been changed on that assumption. **Ask before acting.**

### D-5.2-a — A missing content block renders as nothing

**Decision.** The helper returns null when the block is absent or empty, and the caller drops the
nulls. Not a placeholder and not an error: a half-seeded database, or a block
somebody archived, should leave a page one paragraph short rather than printing `content.missing` in
front of a hundred colleges.

**Moved in Phase 8.** The `RendersContentBlocks` concern became `App\Support\ContentBlocks::render()`
when the Filament `Page` base class it hung off was deleted. A static helper, not a trait: the
callers are now controllers and Livewire components with no common ancestor, and there is nothing
about looking a block up that wants to be mixed into a class. The behaviour is unchanged.

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

**RESOLVED 2026-08-19 — see D-8-d.** The form was rebuilt as a Filament schema on our page,
calling `ContactService::submit()` for the work. Our form validates the consent checkbox before the
service is called, so it is now a real control rather than theatre. No owner decision needed.

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

---

## Fixes after the first look in a real browser (2026-08-19)

The build was verified by 609 passing tests and never opened in a browser. Four things were wrong
that no HTTP test could have caught, because every one of them is about *rendering* rather than
about status codes or content. Recorded here because the lesson generalises: an HTTP smoke proves a
page is served, not that it is legible.

### D-8-a — Filament's assets were never published

**Symptom.** Every page served a 200 and looked like unstyled HTML: `/css/filament/…`,
`/js/filament/…` and `/fonts/filament/…` all 404ed.

**Cause.** Filament arrived *transitively* through `uclemmer/laravel-core`, so its installer never
ran, and nothing ever copied its assets into `public/`. `composer.json` had no
`@php artisan filament:upgrade` in `post-autoload-dump` — the hook Filament's own installer adds.

**Fix.** Ran `filament:assets` and `storage:link`, and added the hook so a fresh `composer install`
does it automatically. **Any app in this workspace that picks Filament up through `core` rather than
requiring it directly has the same hole.**

### D-8-b — Rendered markdown needs `fi-prose`

**Symptom.** Content blocks and FAQ answers rendered as a wall of identically-sized lines. The
`<h2>` and `<p>` were in the HTML and correct; they simply had no styling.

**Cause.** Filament's stylesheet is a Tailwind build with preflight, so bare `h2`/`p` carry no
typography at all. `Schemas\Components\Text` also renders a `<span>`, which is invalid around block
elements and collapses their spacing.

**Fix.** `RendersContentBlocks::prose()` wraps trusted HTML in `<div class="fi-prose">` — Filament's
own typography class — and uses `Schemas\Components\Html`, which emits raw HTML with no wrapper. The
FAQ page uses the same helper, so the two cannot drift.

### D-8-c — Newline-joined text collapses

**Symptom.** The coordinator's postal address ran onto one line; a sponsor's staff list ran its four
names together.

**Cause.** `Text::make("a\nb")` renders inside a span. HTML collapses the newline to a space.

**Fix.** The address is built as HTML with `<br>` and rendered through `prose()`; the staff list is a
real `UnorderedList`. The rule to remember: **if it has more than one line, it is not a `Text`.**

### D-8-d — The contact form is now ours, which resolves D-5.4-a

**Symptom.** The embedded `<x-core::contact-form />` rendered as borderless inputs and a submit
button that looked like plain text, in the middle of an otherwise styled page.

**Cause.** Not a bug. laravel-core ships it "deliberately unstyled beyond structure" — its own
docblock — precisely so a host can style it. That is right for a package and wrong on a public page.

**Fix.** The form is a Filament schema on our page; submitting calls `ContactService::submit()`,
which still owns storage, attribution, the `ContactSubmitted` event, the receipt and the organizer
alert. Presentation ours, logic the package's, and no fork of core's markup.

**This resolves D-5.4-a.** The consent checkbox is now real: our form validates it with `accepted()`
before the service is ever called. The reason it could not be real before was that core's controller
validates only its own fields — an unvalidated checkbox is theatre. The honeypot and an IP throttle
are reimplemented on our submit handler, because a Livewire submit never reaches core's throttled
route and would otherwise have had neither.

**No longer needs an owner decision.**

### D-8-e — Front-end wiring landed before the design (card 8.0)

**Context.** The owner confirmed on 2026-08-19 that the public site becomes Blade + Livewire +
Flowbite and that **the rep portal stays Filament for now**, then asked for the design-independent
groundwork while the Claude Design handoff is prepared.

**Decision.** Only the build pipeline: `flowbite` as a runtime dependency (it ships to the browser,
so `npm ci --omit=dev` must still install it), the plugin and `@source` lines in `app.css`, the
import in `app.js`. Mirrors `ckbs`, which the workspace names as the reference wiring.

`config/livewire.php` was published for one reason. Livewire 4 ships `component_layout => 'layouts::app'`
and that namespace does **not** resolve here — `component_namespaces` registers namespaces for
Livewire's *component* resolution, not Blade view hints, and the runtime hint list confirms
`layouts` is absent. The first full-page component anybody wrote would have died at render time
with "No hint path defined for [layouts]". Pointing it at `components.layouts.app` follows the
pattern `ckbs` and `budget` use, and that path doubles as a Blade component so static pages and
Livewire pages share one layout rather than two that drift.

**The layout itself is a deliberate placeholder** and says so at the top of the file: `@vite`, a
slot, a title, no design. Inventing a look now would only be something to unpick.

**Not done, on purpose:** no pages, no navigation, no components, no colours. All of that waits.

### D-8-f — `APP_URL` must match the serving host

**Symptom found while wiring.** `.env` had `APP_URL=http://coasttocoastcollegefair.test`; Herd serves
this project at `https://coasttocoast.test`. The Blade layout's `@vite` emitted
`http://coasttocoastcollegefair.test/build/...` — a cross-origin, mixed-content reference to a host
that does not exist.

**Why it had not bitten.** Every page so far came from Filament's published assets under
`public/css` and `public/js`, which do not go through Vite. The moment a Blade page calls `@vite`,
it matters on every request.

**Fixed** in the local `.env`. The rule generalises and is in doc 11's environment table: `APP_URL`
is what absolute URLs in assets and in email are built from, so it has to match the host actually
serving the site in every environment.

---

## Phase 8 — the public site rebuild (2026-08-19)

These follow the design handoff in [`docs/design-handoff/`](design-handoff/), whose
README declares colours, typography, spacing and copy final. Where the build departs from it, the
departure is here.

### D-8.1-a — Fonts are self-hosted, not loaded from Google

**Context.** The handoff's three pages each open with `<link rel="preconnect">` to
`fonts.googleapis.com` and `fonts.gstatic.com` and a stylesheet link for Montserrat, Caveat and
Source Sans 3.

**Decision.** Same three families, same weights, fetched at build time and served from this origin —
`bunny()` from `vite-plugin-webfont-dl` in `vite.config.js`, which emits the `@font-face` rules and
the `woff2`/`woff` files into `public/build`.

**Why.** Two reasons, and neither is a matter of taste. A public marketing site that loads fonts from
Google makes every visitor's browser announce itself to a third party before the page paints, and
this site's visitors are largely high school students and their parents. And a cross-origin font
request costs a DNS lookup, a TLS handshake and a render-blocking round trip to an origin we do not
control; self-hosting removes all three. The visual result is identical.

**The trap this hid.** Configuring `fonts:` in `vite.config.js` downloads the faces and writes
`public/build/fonts-manifest.json`, but **nothing reaches the page unless the layout calls `@fonts`**.
The layout shipped without it, and the failure is completely silent — the build succeeds, every test
passes, every page renders, just in the fallback system stack. The only way to see it is to look.
`@fonts` goes *before* `@vite`, so the `@font-face` rules are parsed before the stylesheet that uses
them, and it inlines the declarations rather than linking a second CSS file. Two tests in
`FrontendWiringTest` now pin it, including one asserting no `fonts.googleapis.com` reference.

**To reverse:** drop the `bunny()` calls from `vite.config.js` and the `@fonts` directive from
`components/layouts/app.blade.php`, and put the handoff's `<link>` tags there instead.

### D-8.4-a — The countdown does not poll

**Context.** The handoff's landing page has a days/hours/minutes/seconds countdown to the fair. The
obvious Livewire implementation is `wire:poll.1s`.

**Decision.** No polling. `EventCountdown` renders the correct numbers server-side on first paint,
and an Alpine `setInterval` ticks them in the browser.

**Why.** A one-second poll on the public landing page is one HTTP request per visitor per second,
each one booting the framework, to redraw four numbers the browser can compute itself. On the evening
the fair is announced that is the difference between a busy site and a fallen-over one. Server-side
first paint also means the numbers are right for a visitor with JavaScript disabled and for anything
crawling the page — they are simply frozen at page load, which for a countdown to a date months away
is not a lie.

**The trade-off:** a tab left open for days drifts from the server's clock. It re-syncs on any
navigation, which is enough for this.

### D-8.4-b — The landing page's prose is content, the headline is not

**Context.** The handoff supplies final copy for the landing page. Typing it into the Blade template
is the direct reading.

**Decision.** Split. The hero paragraph and the registration panel's introduction render from the
`home.hero` and `home.for_representatives` content blocks, seeded with the handoff's words. The
headline, the eyebrows and the section titles stay in the template.

**Why.** Everything else on this site that a coordinator might want to change is editable without a
deploy, and that was a deliberate design point (D-5.2-a, doc 03). Hard-coding the design's paragraphs
would have made the landing page — the page most likely to want a seasonal tweak — the one page
needing an engineer, and it would have orphaned two content blocks that already existed. The
headline is different in kind: it is display type, cropped and sized to the layout, and a coordinator
lengthening it would break the hero rather than update it.

### D-8.4-c — The fee appears on the landing page, which the design does not show

**Decision.** The registration panel prints `Event::priceFor()` for the active fair.

**Why.** Doc 00 lists "pricing and deadlines scattered or missing" as a named weakness of the current
site, and doc 01 makes not repeating it a requirement. A representative deciding whether to come
should not have to hunt. It is a small addition inside the design's existing panel and does not
disturb the layout.

### D-8.5-a — A sponsor with no logo shows its name once, not twice

**Decision.** When `logo_path` is null the placeholder tile *is* the caption, rather than a name tile
with the same name printed underneath it.

**Why.** The four school logos have not been supplied (doc 11's asset queue). The first pass rendered
the fallback tile and the caption independently, which read as a bug rather than as a placeholder.
Once the logos arrive the caption returns beneath the mark, as the design has it.

### D-8.5-b — `ContentBlockSeeder` asks `withTrashed()`

**Symptom found while reseeding the landing copy.** Deleting `home.hero` and re-running the seeder
died on `UNIQUE constraint failed: core_contents.type, core_contents.slug`.

**Cause.** laravel-core's `Content` uses `SoftDeletes`, but its unique index is `(type, slug)` and
does not include `deleted_at`. A deleted block therefore still owns its slug. The seeder's
idempotence guard used the default scope, could not see the row, and inserted.

**Why it matters beyond the moment.** `ProductionSeeder` runs on deploy. A coordinator deleting a
content block they did not want — an ordinary thing to do in the admin panel — would have made the
next deploy's seed abort, and it would abort partway, leaving whatever blocks came after it in the
array unseeded.

**Fixed** by scoping the guard `withTrashed()`, with a test in `SeederTest` that deletes a block and
reseeds. The behaviour is now: a deleted block stays deleted, and the seed completes.

### D-8.5-c — The maintenance page is self-contained

**Decision.** `resources/views/errors/503.blade.php` carries its own inline `<style>`, references
images by static path under `public/images/`, and calls no `@vite`.

**Why.** `php artisan down --render=errors::503` renders the view **once** and serves that flat HTML
for the whole outage — which is precisely the window in which `public/build` is being replaced. An
`@vite` call would freeze one moment's hashed asset URL into the file and the maintenance page would
404 its own stylesheet, on the one page whose entire job is to work when nothing else does.

Config is still readable at that point (bootstrapping precedes the maintenance middleware), so the
contact address comes from `config('fair.coordinator.email')` — but note it freezes at render time
and only refreshes on the next `artisan down`.

**Wired into the deploy on 2026-08-19, which it initially was not.** The view existed and plain
`artisan down` found it — the exception handler registers `resources/views/errors` under the
`errors::` namespace — but doc 11's deploy sequence never took the site down at all, so nothing ever
passed `--render` and the deploy-safe mode the page was designed around was unreachable in practice.
The runbook now opens with
`php artisan down --render="errors::503" --retry=60 --with-secret` and closes with `php artisan up`.

Three properties are worth stating because each is a decision:

- **`--with-secret`** prints a bypass URL, so the coordinator can walk the deployed site before `up`
  lifts maintenance for everyone.
- **The static images are served by nginx, not by PHP** — verified while actually down: `/` returned
  503 while `/images/cityscape.jpg` returned 200. That is what lets a page served from a flat file
  still carry a photograph.
- **A failed deploy leaves the site down, on purpose.** No automatic `up` in a `finally`: lifting
  maintenance on a half-migrated database is worse than staying dark.

A test asserts the frozen template contains no `/build/` reference, and another asserts the runbook
still documents the exact command — the view path and the flag live in two files nothing else
connects.

### D-8.5-d — The venue address stays "1150 Carter Street"

**Context.** The handoff's landing page gives the venue address as "1 Carter Plaza". Doc 00, taken
from the live site, and the production seed both say "1150 Carter Street".

**Decision.** Kept 1150 Carter Street, and flagged the discrepancy in doc 11 for the owner.

**Why.** A wrong address on a page whose purpose is getting people to a building is worse than a
stale one, and the live site is the better evidence of what the Convention Center's address actually
is. This is content, so correcting it is an admin-panel edit, not a deploy.

---

### D-9-a — Five past fairs, and the seeder stopped deriving slugs from years

**Owner request, 2026-08-19.** "The fair needs to be a database record. There needs to be a fair for
the last 5 years. I will import previous year registrations later. There might come a time where we
have two fairs a year."

**What was already true.** The fair has always been a database record — `App\Models\Event`, one row
per fair. `EventSeeder` seeded three of them (2025, 2026, 2027).

**Decision.** Extended `EventSeeder` rather than adding a second seeder. It is already the one thing
that owns the fair calendar, and it is idempotent by slug; a parallel seeder writing to the same
table would have been two places to look and a way to double-seed. It now writes six fairs — 2022
through 2026 published and past, 2027 unpublished as before.

**Why the back catalogue matters.** `fair:import-roster` resolves each CSV row by `event_slug` and
**skips** rows naming a fair that does not exist. Without a 2023 fair there is nowhere to put a 2023
roster, and the skip is a warning rather than an error — so the import would look like it worked.
Seeding the history is a precondition of the import the owner has planned, not decoration.

**On the reconstructed figures.** Only 2026 is confirmed (Tuesday 21 April 2026, $215, from the live
site); 2022–2025 are plausible reconstructions on the same fourth-Tuesday-of-April pattern with the
fee stepping down. This is deliberately tolerable, because **nothing downstream reads a past fair's
`price_cents`** — a registration snapshots what it actually paid, and the import CSV carries a
per-row `price_cents`. A past fair's list price is a record, not an input, and it is an admin edit.

**Two fairs a year: supported already, and now proven.** No code changed. Nothing in the application
groups fairs by year — `Event::active()`, the `previousPublished()` scope behind the Last Year
roster, and every cross-year audience all order on `starts_at`. The one thing a year bought was the
slug, so the seeder now writes each slug and name out per fair instead of deriving
`college-fair-{year}`, which would have collided on the unique index the day a year held two. Three
tests in `EventTest` pin it: handover from a spring fair to a fall fair, "previous" meaning the
previous *fair* rather than the previous year, and two fairs coexisting in one calendar year.

**Not done, deliberately:** the public roster page stays routed at `/last-year` and labelled "Last
year". Its logic is `previousPublished()`, which already means "the previous fair" and stays correct
with two fairs a year — only the wording would read oddly. Renaming it breaks a public URL, so it is
the owner's call rather than a tidy-up.

---

### D-9-b — The honeypot now costs an attempt, and trusted proxies are configurable

**Owner question, 2026-08-19:** "does the contact form have a honeypot and a race counter?" Both
were there. Checking properly turned up two faults behind them.

#### The limiter counted only successes

`RateLimiter::hit()` ran *after* the honeypot check, so a submission that tripped the honeypot was
told "something went wrong" and never counted. A bot could retry indefinitely, booting the framework
on every attempt. The limiter was guarding the expensive path — storage plus two emails — but not
the cheap-to-repeat one.

**Decision.** Check the limit, **increment, then examine the honeypot**. A honeypot trip now spends
its allowance like any other submission. A visitor never fills that field, so this costs them
nothing, and a bot that has identified itself gets five attempts an hour instead of unlimited.

**Validation failures still do not count**, deliberately — they throw before the limiter is reached,
and somebody mistyping their email three times should not burn an hour's allowance. Both halves are
tested.

**Extracted to `App\Livewire\Concerns\ThrottlesPublicSubmissions`** rather than fixed twice. The
contact form and the interest capture held identical copies of this logic, which is exactly how one
ends up fixed and the other does not. The honeypot field name lives there too, so the two forms
cannot disagree about the one thing a bot author would need to know. `MAX_ATTEMPTS_PER_HOUR` is also
asserted against the `throttle:5,60` on the plain interest POST — the non-JavaScript path writes to
the same table, and a limit that only guards the JavaScript path is not a limit.

#### `TrustProxies` was never configured

Every one of those limits keys on `request()->ip()`, which behind a load balancer or CDN is the
**proxy's** address until the proxy is trusted. Every visitor would share one throttle bucket, and
the fifth contact message of the hour from anybody would lock out everybody. Herd hides this locally
because there is no proxy.

**Decision.** `TRUSTED_PROXIES`, off by default. Off rather than `*` because the wrong answer is
dangerous in *both* directions: trusting nothing behind a proxy throttles the whole internet as one
visitor, and trusting `*` on a directly reachable host lets anyone forge `X-Forwarded-For` and mint a
fresh bucket per request — which removes the limit rather than loosening it. The topology is the
owner's to know, so doc 11 carries a table of the three cases and how to verify the result.

**Where it is read is the interesting part, and both obvious places are wrong.**
`bootstrap/app.php`'s `withMiddleware` closure runs while the kernel is being resolved, *before* the
config repository is bound — `config()` there is a fatal, which is how this was found. And `env()`
there silently returns null the moment `config:cache` runs, because caching config stops `.env` being
loaded at all: it would have worked in development and quietly done nothing in production, the one
environment the setting exists for. It is therefore read in `AppServiceProvider::boot()`, where
config is available and which is comfortably before any request is handled, via `TrustProxies::at()`.

Three tests: the default ignores `X-Forwarded-For`, a trusted proxy is honoured, and the value still
comes through `config/fair.php` rather than an `env()` call at the point of use.
---

### D-9-c — The FAQ attachment is on the private disk, behind a route

**Owner request, 2026-08-19:** add the W-9 upload to the FAQ. Doc 11's owner queue had said "Admin →
FAQ (and a file to upload)" since it was written, and the FAQ screen had no upload at all — the panel
could not keep a promise the runbook was making on its behalf.

**Generic attachment, not a `w9_path`.** `faq_items.attachment_path` and `.attachment_name`. The W-9
is the document that exists today; a floor plan, a parking map and a conduct policy are the same
shape, and a column named after one document has to be joined by another the first time a second
appears. The upload accepts a PDF up to 5 MB, validated with `mimes:pdf` — which reads the file, not
the extension the browser claimed.

**Stored on the private disk and served by `FaqAttachmentController`, not linked from
`Storage::disk('public')->url()`.** The public-disk version is one line and was the obvious build.
It was rejected because a public URL keeps serving for ever: unpublishing the question would hide it
from the page and go on handing out the file to anyone holding the link. A signed W-9 carries the
fair's EIN and an authorised signature — not a secret, it is given to every college that asks, but
"the coordinator took it down" should mean it is down.

The route is still unauthenticated. This is not access control; it is making withdrawal work. It
costs a framework boot per download, which on this page is a rare event.

The controller 404s rather than 403s throughout, matching how an unpublished fair is hidden
(D-5.4-c), and 404s when the **row outlives the file** — a database restore without the storage
directory, or a file removed by hand. Without that guard a visitor gets a 500 on a link the page
itself rendered.

**`attachment_name` exists because the stored name is randomised.** Somebody filing a W-9 into an
accounts-payable system needs `coast-to-coast-w9.pdf` back rather than a hash, and
`Storage::download()` takes the name to serve it under.

**Replacing or clearing an attachment deletes the old file**, mirroring
`Sponsors\Edit::deleteStoredLogo()`. Nothing else references that path, so nothing else would ever
delete it.

Verified in a browser as well as by the nine tests: the download returns 200 with
`Content-Type: application/pdf` and `Content-Disposition: attachment; filename=coast-to-coast-w9.pdf`,
the file sits in `storage/app/private/faq-attachments/`, and `/storage/faq-attachments/` is a 403 —
it is not on the public disk at all.

**Two follow-ons recorded rather than done.** `storage/app/private` was added to doc 11's backup
list, because the row is worthless without the file. And the development database now carries a
68-byte placeholder PDF on the W-9 question, deliberately named
`SAMPLE-replace-with-the-real-w9.pdf` — it demonstrates the affordance and cannot be mistaken for a
real tax document.