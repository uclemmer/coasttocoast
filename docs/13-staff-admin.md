# 13 — The staff area, and getting the fair's admin off Filament

**Status: complete, 2026-08-21. `app/Filament/` is deleted and the fair's admin
lives at `/staff`.**

This is step 3 of the workspace Filament removal (`CLAUDE.md`). Doc 12 covered
step 1 — the rep panel at `/portal`. This one covers the fair's own admin
screens: `app/Filament/Admin/` plus `FairPlugin`, 37 files and ~2,910 lines,
attached to `laravel-core`'s prebuilt `/admin` panel through
`core.admin.plugins`.

## Where the screens live, and why not `/admin`

**`/staff/*`.** `/admin` is not ours to take: it is core's panel, and it still
serves core's own modules — users, roles, the email log, content, settings,
contact. Those go when core goes headless, which is step 4 and a separate,
larger change.

So there are **two staff surfaces** for now, and the thing that keeps that from
being confusing is that they agree on who is staff.
`Staff\Concerns\ActsForStaff::abortUnlessStaff()` asks the same `admin.access`
permission that `User::canAccessPanel()` asks for the `core` panel. Somebody
who can reach one can reach the other. The account dropdown links across.

Unlike `/portal`, there was no path to preserve — these screens lived inside
Filament's own URLs and nobody bookmarked them — so the prefix was free to
choose.

## What this step does NOT touch

Filament stays installed and `/admin` keeps working:

- `User implements FilamentUser` and `canAccessPanel()`
- the `HasLabel`/`HasColor` interfaces on the nine enums in `app/Enums`
- `tests/Pest.php`'s Filament panel helpers
- `filament/filament` in composer, and the `filament:upgrade` hook

`FairPlugin` and its `config/core.php` entry come out when the last resource is
migrated, not before.

**The Filament resources are still live, so their tests stay too.** The five
sponsor tests in `tests/Feature/Admin/ContentResourcesTest.php` were *ported* to
`tests/Feature/Staff/SponsorsTest.php`, not moved — deleting coverage of code
that is still running and still reachable would be worse than the duplication.
They go when `app/Filament/` does.

## The pattern, established on Sponsors

Sponsors first because it is the smallest full CRUD that still exercises the
widest set of the hard mechanics — file upload, reordering, a nested collection,
bulk selection — so a wrong pattern surfaces on 200 lines rather than on the
seventh resource.

- **Full-page Livewire components**, `#[Layout('components.layouts.staff', …)]`,
  routed as the route action. Same as the portal.
- **`#[Computed]` for reads**, `unset($this->x)` after a write.
- **`$this->authorize()` explicitly, in `mount()` and again in every action.**
  This is the single most important difference from Filament, which resolved
  policies implicitly — a resource with a policy got `viewAny`/`view`/`create`/
  `update`/`delete` checked for free. Livewire does not. A forgotten
  `authorize()` is silent: the screen simply works for someone who should not
  have it. `SmokeTest` sweeps every `/staff` URL as a representative and expects
  403 from all of them, which is the backstop for exactly that.
- **Records resolved through a scoped query**, never `Model::find()` on an id
  from the browser. `SponsorStaff` is resolved as `$this->sponsor->staff()
  ->find($id)`; without the scope a crafted id edits somebody else's row.
- **Policies take a model.** `$this->authorize('update', Sponsor::class)` throws
  rather than failing closed, because `SponsorPolicy::update()` has a second
  parameter. Authorise against the record.
- **Modals are window events** — `$this->dispatch('ui-modal-open', id: '…')`.
- **`toast()` in the concern** is the only place that knows the package's event
  contract.

### Reordering is buttons, not drag

Filament's tables were `->reorderable('sort_order')` and its comment recorded
the intent: the coordinator should never have to work out what integer puts a
school second. Buttons satisfy that. Drag costs two things buttons do not — it
is unusable by keyboard and by screen reader, and it cannot be exercised in this
project's headless browser, which does not composite frames and so never fires
the animation frames a drag depends on. Alpine's `sort` plugin ships inside
Livewire's bundle if drag is ever wanted **on top**; it should not be instead.

Reordering rewrites the whole column from the resulting order rather than doing
arithmetic on `sort_order`, because `ordered()` breaks ties on name and two rows
may legitimately share a number. It is hidden while a search is active: "move
up" in a filtered list means nothing the user can predict.

### What Filament was doing for free

Each of these is now ours, and each has a test because none of them had one:

| Filament gave us | Now |
| --- | --- |
| `FileUpload` cleanup | `deleteStoredLogo()` — a replaced logo is nothing's reference, so without this the old file stays on disk forever |
| Relation-manager scoping | `$this->sponsor->staff()->find()` |
| Implicit policy checks | `$this->authorize()` in mount and every action |
| A hidden label on a selection checkbox | `aria-label` with no `label` prop — the component always renders a visible label when given one |
| Friendly field names in validation messages | `validationAttributes()` — a Filament `Select` knew its own label, a Livewire property does not. Missed on three components until 2026-09-01; see the last section |

## Traps recorded for the six that remain

- **Dollars↔cents lived on the Filament field** (`formatStateUsing` /
  `dehydrateStateUsing`) for the event price, the grant custom price, and the
  check and refund amounts. `EventResource`'s own comment records that moving
  this to page hooks once **saved every fair at $0**. In Livewire it is an
  explicit mount/save hook per form, and it needs a test asserting stored cents.
- **`Message.channels`' cast** exists because Filament's CheckboxList
  round-trips scalars (`app/Models/Message.php`). Verify before changing it.
- **Nine action modals** carry their own ad-hoc schema. `x-ui::modal` and
  `x-ui::confirm-modal` are the primitives.
- **Persistent notifications.** Filament used `->persistent()` for the
  short-check and merge-collision cases. A toast auto-dismisses; those belong in
  an `x-ui::alert` on the page instead.
- **The CSV export must honour the active filters.** Filament used
  `getFilteredTableQuery()`, so the Livewire query builder has to be shared
  between the table and the export rather than rebuilt.
- **`GrantResource` has no create or edit**, deliberately — list and view only.
- **Pagination is not published yet.** `vendor:publish --tag=ui-pagination` and
  `$rows->links('vendor.pagination.ui')` when the first genuinely long list
  arrives. Sponsors is hand-ordered, and paginating a hand-ordered list is a
  worse answer than not paginating it.

## Done so far

**Sponsors** established the pattern. **The FAQ** added three things to it:

- **`x-ui::forms.markdown` is a styled textarea, not a rich editor** (the
  owner's call when the package was built, docs/12), so what a coordinator
  types and what a visitor reads are not the same string. The edit screen
  renders a live preview through the same `Str::markdown()` the public page
  uses, and a test asserts they agree — if either is ever swapped for a
  different renderer, that is what notices.
- **Publishing moved into the list.** Filament's toggle lived only in the form,
  so hiding a question meant opening it. Taking a wrong answer off the public
  page is the one thing done to a FAQ row in a hurry.
- **Reordering is refused while *any* filter is active**, not only a search.
  Filtering by published state hides rows too, and "move up" past a hidden
  neighbour is just as unpredictable.

`ReordersRecords` was extracted at the third use — sponsors, sponsor staff, FAQ
— not the first. Authorization deliberately stays at the call site: burying a
policy check inside a shared helper is exactly the wrong place for the thing
Filament used to do invisibly.

### Grants, and the first real action modals

Three decisions over `GrantService`, shared by the queue and the detail screen
through `Grants\Concerns\DecidesGrants`. No create or edit, deliberately — an
edit form could set `status = approved` without a benefit, which
`Event::priceFor()` reads as "no discount", so the organization would be told it had a
grant and then charged in full.

Four things worth carrying to the remaining four:

- **Conditional fields become conditional RULES.** Filament said `visible()` and
  `required()` on each amount field; the Livewire version assembles the rule set
  from the chosen benefit, so the field appearing and its rule applying are one
  statement instead of two that can disagree.
- **Service messages are surfaced verbatim** as a danger toast. There is then no
  second copy of the wording to drift from the rule.
- **The detail screen re-reads after a decision** rather than trusting the
  instance it mounted with. Showing "Pending" beside a toast saying "Approved"
  is how somebody clicks twice.
- **A `authorize()` no test can reach still needs a test.** `GrantPolicy::update`
  and `::viewAny` are the same question today, so nobody can pass `mount()` and
  fail the check inside the decision — deleting that check left all twenty other
  tests green. It is not redundant: it is where the decision lands, and it is
  what will enforce `update()` the day that policy grows a condition. The test
  binds a policy that allows the page and denies the update.

### Fairs, and the money conversion

The fee is typed in dollars and stored in cents. Filament put that conversion on
the *field* and its comment says why: a field marked `dehydrated(false)` never
reaches `mutateFormDataBeforeCreate()`, so doing it in the page classes
**silently saved every fair at zero**. The Livewire equivalent of that mistake
is converting in one of mount/save and not the other, so both live in
`Events\Edit`, next to each other, and the tests assert the **stored integer**
in both directions. Falsified both ways before moving on.

The slug is suggested from the name **while creating only** — existing links are
out in the world and renaming a fair must not break them.

The announcement is idempotent by design and each recipient is stamped as the
mail goes out, not in bulk afterwards: if it dies halfway the people already
told are marked and a re-run continues. A coordinator unsure whether the first
press worked should be able to press again.

### Organizations, and the collision that must not be a toast

`OrganizationService::merge()` repoints representatives, registrations and
grants and then deletes the husk — a delete could never do this, because the
foreign keys cascade and would take real financial history with them.

It reports back any fair where the merge has left the organization holding **two live
registrations**, and those are deliberately not resolved automatically: which of
two paid registrations an organization keeps is a decision about money. Filament raised
a `->persistent()` notification. A toast auto-dismisses, so the rebuild keeps
the warning in an `x-ui::alert` on the component until somebody dismisses it by
hand, and a test asserts it survives the next interaction.

Two smaller things:

- **The duplicate warning is surfaced, not blocking** (R2.7), and now updates
  while the name is typed rather than only on a saved record — "Boston
  University" and "Boston College" normalize differently on purpose, so a match
  is worth a second look and never a veto.
- **Self-merge is refused by the service, not restated as a validation rule
  here.** The first attempt added `different:merging`, which both duplicated a
  decision the service already owns and did not actually fire. The service's
  message is shown verbatim instead.

### Campaigns, and two guards in the right order

A sent campaign is immutable: no edit screen reaches one and it cannot be
deleted. It is the record of what a hundred organizations were told, and the delivery
table beside it only means something if the message still says what was sent.

**The already-sent check runs BEFORE `authorize()`, and the order is the point.**
`MessagePolicy::update()` refuses a sent campaign too, so authorising first made
the friendly branch unreachable and a stale tab clicking Send got a bare 403.
Both guards are real; the toast is the one a person should meet. Nothing leaks
by answering first — reaching the screen at all required `view`.

Three more:

- **Channel-conditional bodies** are the same pattern as the grant amounts: the
  rule is assembled from the chosen channels rather than written twice as
  `visible()` and `required()`.
- **The audience count says "Choose an audience" rather than "0"** when nothing
  is chosen. Zero is a real and alarming answer and should not be shown when the
  question has not been asked.
- **The audience preview moved out of a modal onto the page.** The answer to
  "who gets this" should not need a round trip.

This is also the **first paginated screen**, so `vendor:publish --tag=ui-pagination`
finally happened here rather than up front — a hand-ordered list had no business
being paginated, and publishing views nothing used would have been worse.

### Registrations, and one query for two consumers

The CSV export exists so a coordinator can filter, press export and get *that*
list. Filament did it with `getFilteredTableQuery()`; here `filteredQuery()` is
the single builder the table and the export both read, so they cannot drift.
Rebuilding the filters beside the export is the bug that note exists to prevent,
and the test falsifies it by doing exactly that.

Streamed rather than queued for the same reason: an export that arrives by email
a minute later, ignoring the filters, is a different feature. Fair sizes are in
the hundreds.

Manual entry does not write the model — `RegistrationService::createManualEntry()`
does, so the same rules the portal follows apply: duplicates refused, price read
from the fair and any approved grant. A duplicate is reported on the organization
field, because that is the field to change.

There is no delete. A registration is cancelled through the service so the seat
is released and the record of what happened survives.

### The dashboard

The two Filament widgets became one Livewire page at `/staff`. The money numbers
come from **registrations, not the payments table**: "collected" means the price
each organization was quoted and confirmed against, so it agrees with what the
coordinator told them. The payments table answers a different question and would
disagree by whatever is in flight. Checks are separated out as money in the post
rather than money lost.

## Done — what came out, and what did not

`app/Filament/` is deleted: 37 files, seven resources, three relation managers,
two widgets and `FairPlugin`. `core.admin.plugins` is now empty.

**Filament is still installed**, and this is the part to keep straight:
`laravel-core` hard-requires it and still serves `/admin` for users, roles, the
email log, content, settings and contact. Removing it is step 4 of the workspace
order, and it is a package change, not an app one.

Two things stayed behind deliberately:

- `User implements FilamentUser` and `canAccessPanel()` — core's panel calls it.
- The `HasLabel`/`HasColor`/`HasDescription` interfaces on the nine enums in
  `app/Enums`. Nothing renders those enums through Filament any more, so the
  interfaces are now unused markers; the **methods** must survive regardless,
  because `SendRegistrationNotifications` and the staff views call `getLabel()`.
  Drop the interfaces with step 4, not before — there is no benefit to churning
  nine files twice.

### What happened to the Filament tests

Three different treatments, chosen per file rather than in bulk:

| Treatment | Files |
| --- | --- |
| **Deleted** — coverage moved wholesale to `tests/Feature/Staff/` | `Admin/{DashboardWidgets,EventResource,GrantResource,OrganizationResource,RegistrationResource}Test.php` |
| **One block cut**, the rest kept because it tests services and jobs | `Admin/ContentResourcesTest` (sponsors, FAQ), `Notifications/CampaignTest` (the composer), `Payments/CheckPaymentTest` (the admin action) |
| **Repointed**, because it covers cases the ported tests do not | `Notifications/InterestAnnouncementTest` — another fair's list, and the two not-offered cases |

`Foundation/CoreIntegrationTest` was **inverted**: it asserted the fair plugin
was attached, and now asserts it is not, while still asserting core's own is.
`SmokeTest`'s named-page list moved from `/admin/*` to `/staff/*`, keeping
`/admin` itself.

739 tests pass. The count fell from 826 because the ported originals went with
their code; every assertion in them has an equivalent under `tests/Feature/Staff/`.

## A bug this work turned up

`StripeCheckoutService` built its Stripe return URLs from
`App\Filament\Rep\Resources\RegistrationResource`, which commit `582bb13`
deleted when the rep panel was retired. The live card-payment path had been
raising `Class not found` since, and 670 green tests said nothing — the three
tests that construct that service all exercise guard clauses that throw before
those methods are reached, and every other payment test binds
`FakePaymentGateway`.

Fixed and covered by `tests/Feature/Payments/StripeReturnUrlTest.php`. The
lesson is narrower than "add tests": **a method no test ever calls can be
deleted out from under**, and coverage *around* it is not coverage *of* it.
Worth remembering for the six resources still to come, each of which will strand
call sites as it lands.

## A second bug, found by a browser pass two weeks later

**2026-09-01.** Submitting the manual-registration form empty said:

> The organization **id** field is required.

Laravel derives a validation attribute name from the key, so `organization_id`
and `event_id` named foreign keys the form never shows — while the selects above
them were labelled "Organization" and "Fair". Three components had it:
`Staff\Registrations\Create` (both fields), `Portal\CreateRegistration` and
`Staff\Messages\Edit`. `Auth\Register` was the only component that had ever
defined `validationAttributes()`.

The first pass fixed only the `_id` fields, on the reasoning that "the rep name
field" is at least readable where "the organization id field" is not. That line
did not survive being looked at: `rep_` is a column prefix, the inputs are
labelled plainly "Name", "Email" and "Phone", and each message renders directly
beneath its own input — so the label is the only word there is to match it
against. Both registration forms now name **every** field as the form labels it,
not just the ones that were unreadable.

A third pass found the campaigns form still doing it in three more places —
`channels`, `email_body` and `sms_body`, under inputs labelled "Send by",
"Email" and "Text message". Each pass found the previous one's leftovers, which
is what a rule applied by hand does; **the sweep that would have found all of
them at once is to submit each form empty and read what comes back**, and it
takes a minute per screen.

`channels` is the exception worth recording, because it is where the rule stops.
Its label is "Send by", and "the send by field is required" is not English — the
heading sits above a checkbox list and is not a noun for the thing being
validated. It takes a written name, **delivery method**, and there is a test
pinning that specifically, because the obvious tidy-up is to make it match the
label like everything else. Matching labels is a means to a message somebody can
act on, not the point in itself.

**This belongs in the table above.** A Filament `Select` was constructed with its
label and used it for both the field and its messages; a Livewire property is
just a property, and the label lives in the Blade where the validator cannot see
it. It joins the other four things the port had to take over by hand — and it is
the one that got missed, because unlike file cleanup or policy checks it is
invisible until someone submits a form wrongly.

Two things worth keeping from how it was found and fixed:

- **The rename browser pass found it, and nothing else could have.** 799 tests,
  Pint and a full-source vocabulary scan were all green: nothing was *broken*,
  the wording was just wrong, and no assertion had ever read it. `assertHasErrors()`
  passes on the old message and the new one alike — so the tests added here assert
  the **message text**, which is the only thing that can catch it coming back.
- **It predates the schools→organizations rename** (doc 17) and is committed
  separately. The field has been `organization_id` since card 1.2, so the screen
  said "organization id" before the rename too. Fixing it in the rename commit
  would have implied the rename caused it.
