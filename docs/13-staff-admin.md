# 13 — The staff area, and getting the fair's admin off Filament

**Status: in progress. The shell, Sponsors and the FAQ are done (2026-08-21);
five resources remain.**

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

## Order of the rest

`Grant` (three modal actions over
`GrantService`, a filter defaulting to Pending, a nav badge) → `Event` (reactive
slug, dollars↔cents, infolist, the announce action) → `Organization` (merge,
duplicate detection) → `Message` (live `AudienceBuilder` count,
channel-conditional fields, send/test) → `Registration` (largest; the filtered
CSV export). Then the two widgets as `x-ui::stat-group`, delete `app/Filament/`,
and remove the `FairPlugin` entry from `config/core.php`.

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
