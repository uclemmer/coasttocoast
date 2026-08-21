# 12 — Adopting `uclemmer/laravel-ui`, and retiring the rep panel

**Status: complete 2026-08-21. The Filament rep panel is gone.**

This doc covers the workspace's 2026-08-20 directive as it lands here: the UI
stack is Tailwind + Alpine + Livewire, Filament and Flowbite both leave, and
every UI surface is built on `uclemmer/laravel-ui`. It supersedes golden rule 1
in [README.md](README.md), which still says "backend is Filament".

The first target is the **rep portal at `/portal`**, because it is a non-staff
surface and the workspace removal order puts those first — it needs no package
change and nothing else depends on it.

## What the rep panel actually is

Worth stating plainly, because "remove a Filament panel" undersells it by an
order of magnitude. `app/Filament/Rep/` plus `RepPanelProvider` is **1,592 lines
across 11 files**, and it owns **the whole authentication surface**:

| Surface | Today | After |
| --- | --- | --- |
| Login, logout | Filament | `laravel-core` (routes exist, currently disabled) |
| Password reset | Filament | `laravel-core` |
| Two-factor | — | `laravel-core` gains it for free |
| **Registration** | Filament + D9 school claim/create | **app-owned** — the logic is entirely app-specific |
| **Email verification** | Filament | **app-owned** — see below |
| Profile (phone, SMS opt-in, self-retire) | Filament | app-owned; core's account routes cover only the generic half |
| Dashboard, registrations, grants, org profile | Filament resources | Livewire + `laravel-ui` |

There is no Fortify, Breeze or Jetstream in this application. Filament is the
only thing standing between a visitor and a session, which is why the auth
question had to be settled before any screen was rebuilt.

### Email verification is app-owned, and that is not a preference

**Neither `laravel-core` v0.2.0 nor v0.3.1 has email-verification routes** —
checked directly, not assumed. So bumping core would not supply it, which
usefully takes the core upgrade off this change's critical path. Laravel's own
`MustVerifyEmail`, its notification and its middleware do the work; what is
missing is three routes and a notice view, and those belong here.

## Groundwork done 2026-08-21

### `uclemmer/laravel-ui` v0.4.0 installed

The first **production** app to take it. A `vcs` repository plus a tagged
constraint, per the workspace rule that production apps never use a path
repository:

```json
{ "type": "vcs", "url": "https://github.com/uclemmer/laravel-ui.git" }
```

```
"uclemmer/laravel-ui": "^0.4"
```

It pulls in nothing — no core, no Filament — so it is the one package that can
be added here without widening the dependency graph.

### The theme sheet is published and repointed at the design handoff

**This is the part worth reading before changing anything visual.**

laravel-ui's components are written in semantic tokens (`bg-brand`,
`text-body`, `rounded-base`), and the package's default sheet maps every one of
them onto Tailwind's stock palette — where brand is **blue**. This site's brand
is the green from the wordmark, declared final by the Claude Design handoff.
Left at its defaults, every component the package ships would have rendered
blue on a green site.

So the sheet was published and is now owned here:

```bash
php artisan vendor:publish --tag=ui-theme     # → resources/css/vendor/ui/theme.css
```

and every one of its 90 colour declarations repointed at this app's own tokens —
`--color-brand` → `--color-brand-600`, `--color-heading` → `--color-ink-900`,
`--color-danger` → the handoff's `--color-danger`, and so on. **Nothing there
invents a colour.** Change a value in `app.css` and it flows through to every
component. Verified in the built stylesheet: `bg-brand` resolves to `#188042`.

Two consequences, both in that file's header:

- **It no longer receives package updates.** A token added by a later
  laravel-ui release will be missing here and its classes will compile to
  nothing, silently. When upgrading the package, diff the published copy
  against `vendor/uclemmer/laravel-ui/resources/css/theme.css`.
- **The `.dark` block is a deliberate no-op**, every dark value mirroring its
  light one, because this site has no dark mode and a half-built one is worse
  than none.

`success` borrows the brand green: the handoff has no success colour, and green
already *is* the positive colour here. `pink` and `purple` stay on Tailwind
stock — they are badge accents with no equivalent in the handoff, and inventing
two hues to avoid two stock values would be worse.

### `app.css` gained one import and one `@source`

```css
@import './vendor/ui/theme.css';                              /* the tokens */
@source '../../vendor/uclemmer/laravel-ui/resources/views';   /* Tailwind skips vendor/ */
```

Both fail **silently** if missing — components render with a full `class`
attribute and no styling, and nothing anywhere errors. The import sits at the
top because CSS requires imports before other rules; the app tokens it
references are declared much further down in the same file, which is fine, since
a custom property resolves when it is used rather than when it is parsed.

### Flowbite stays, for now, on purpose

Only two files still depend on Flowbite's JavaScript: the mobile nav's
`data-collapse-toggle` in `components/site/header.blade.php`, and the FAQ's
`data-accordion-*`. **laravel-ui has no accordion** — it was never built,
because no two apps hand-rolled one — so removing Flowbite means either building
that component upstream or hand-rolling Alpine here. Either is a separate
change with its own reasoning, not a side effect of adopting the package.

The two coexist cleanly: different class vocabularies, neither aware of the
other. When Flowbite does go, the recipe is in laravel-ui's
`docs/03-host-integration.md`.

## Auth — done 2026-08-21

**Core's half.** `core.auth.routes.enabled` is on with an empty prefix, so log
in is at `/login`, not behind the package's default `core` prefix — that prefix
is for apps whose own auth already occupies those paths, and this one had none.
Signing in lands in `/portal`; signing out returns to the site. Two-factor comes
along for free and is not wired to anything yet.

`bootstrap/app.php` names both redirect targets explicitly, because core names
its route `core.login` and Laravel's `auth` middleware looks for one named
literally `login`. Without it a guest hitting a protected page gets a
`RouteNotFoundException` — a 500 where a redirect belongs.

**The views.** Core ships its auth views deliberately unstyled and
dependency-free so a host can restyle them, which is what happened: log in,
forgot password and reset password are published and rebuilt on laravel-ui.
Only `auth/` was kept — `vendor:publish --tag=core-views` copies everything the
package has, and each published file stops receiving updates.

**Registration is app-owned**, in `App\Livewire\Auth\Register`, because signing
up claims or creates a school and that decides whether the account is active
immediately (D9). The component collects fields and calls
`OrganizationService::claim()` / `::createWithFounder()` — which path makes
somebody active stays one decision in one place, exactly as it was under
Filament.

Two things it does differently from the Filament page, both forced:

- **The school picker is a search box and a list, not a select.** Filament gave
  this a server-searching select for free. A plain `<select>` would render every
  school in the country into the page, and a `datalist` cannot report *which*
  row was chosen — only what was typed, which is not an id.
- **Validation is built in `rules()` rather than `#[Validate]` attributes.** An
  attribute cannot be conditional, and requiring both `organization_id` and
  `organization_name` would make each path fail on the other's field.

The honeypot and rate limit live in the component for the same reason
`ContactForm`'s do: a Livewire submit never touches a throttled route.

**Email verification is app-owned too**, and it turned out to be required
immediately rather than later: firing `Registered` triggers Laravel's
verification-notification listener, which needs a `verification.verify` route to
build a URL against. Without it, registration itself throws. Three routes over
Laravel's own machinery, in `EmailVerificationController`.

The security property worth knowing: `EmailVerificationRequest` checks that the
id and hash in the signed URL match the *signed-in* user. Without that a valid
link would verify whoever happened to be logged in, which is how one person
verifies another's address. Pinned by a test.

**One shared layout**, `components/layouts/auth.blade.php`, used both by the
Livewire sign-up page (via `#[Layout]`) and by core's published Blade views
(via a thin `core::auth.layout` wrapper). A sign-up page that does not match the
log-in page beside it looks like a different site.

## Portal screens — four of six built 2026-08-21, none wired yet

Built and passing lint, **not yet routed**: `Portal\Dashboard`, `Portal\Grants`,
`Portal\Profile`, `Portal\OrganizationProfile`, plus the shared
`Portal\Concerns\ActsForAnOrganization` and a `components/layouts/portal`
shell.

**Why nothing is routed yet.** Filament's rep panel owns `/portal` and every
path under it. Registering competing routes is not a thing Laravel resolves
sensibly, so the routes and the panel's deletion have to land in one change —
which means every screen has to exist first. Until then these components are
unreferenced files and the suite is unaffected.

What carried over unchanged, because they are product rules rather than panel
mechanics: the membership gate (pending and retired reps browse but cannot act),
`membershipNotice()`'s copy verbatim, and Appendix A's grant status sentences.
Actions call `GrantService` / `OrganizationService` exactly as the Filament
pages did — which path makes somebody active, and what withdrawing means, stay
one decision in one place.

Two things worth knowing about the port:

- **`actsForOrganization()` is checked inside every action, not only in the
  view.** A hidden button is a UI convenience, not a guard.
- **`withdraw()` scopes its lookup to the school's own grants** rather than
  `find()`. The id arrives from the browser, and a confirmation dialog is not
  authorization.

### Still to build: registrations

`Portal\Registrations` (list), the create flow, and the detail page. This is the
largest piece by a distance and the only one touching money:

- the create page is a **multi-step wizard** ending in a **Stripe handoff**;
- pricing is grant-aware and comes from `Event::priceFor($organization)`,
  server-side, snapshotted onto the row;
- a gateway failure must leave a recoverable `pending_payment` registration
  rather than losing the whole thing, with a retry button on the detail page;
- the detail page also carries the check-payment form and the receipt.

laravel-ui has **no stepper or wizard component** — it was never built, for the
same reason as the accordion. Whether the create flow needs one, or works as a
single page with sections, is the first decision that phase faces.

## Done 2026-08-21 — the panel is deleted

All six screens are Livewire, routed at `/portal` **at the URLs Filament
served**, so bookmarks and emailed links still land. `app/Filament/Rep/` and
`RepPanelProvider` are deleted; 668 tests green.

The registration flow is **one page with three sections, not a wizard** (owner
decision). laravel-ui has no stepper, and a form that ends in a payment is
better showing the whole commitment at once. **A wizard component is still owed
to laravel-ui's roadmap** for whatever genuinely needs one.

### What the port taught

**The old tests were kept and ported, not replaced.** They cover product
behaviour — who sees whose registrations, what a grant does to a price, what
happens when Stripe is down — and only the harness was Filament's. One file,
`RepPanelAccessTest`, was briefly deleted and then restored: its subject was
gone but every rule it pinned still holds.

**Two scoping guarantees were strengthened rather than merely preserved.**
`ShowRegistration` re-resolves through a scoped `findOrFail` instead of checking
the bound model, so a foreign registration is *not found* rather than
found-and-refused — the second answer is an oracle confirming the id is real.
`Grants::withdraw()` scopes the same way: the id arrives from the browser, and a
confirmation dialog is not authorization.

**Scoping tests now assert against the component's collection, not the page.**
A fair's *name* can legitimately appear on a portal page without that school's
record being visible — the grants page lists every fair you could apply for — so
string-matching HTML was testing the wrong thing.

**Three traps worth carrying forward:**

- `@disabled(...)` **inside a component tag** emits its own `endif` into the
  component's compiled wrapper and breaks the file. Same family as Blade's
  nested-comment trap.
- In a Livewire view, `$this->foo` resolves **computed properties only**. A
  plain trait method needs `$this->foo()`, and getting it wrong is a 500 that
  only shows on the page using it.
- A typed `string` property cannot be `set(..., null)` in a test; the empty
  string is the "not filled" state.

## Flowbite removed 2026-08-21

The two things holding it here were the public site's mobile nav and the FAQ
accordion, and `app.css` said so in as many words: *"laravel-ui has no accordion
to replace the second, so removing Flowbite is its own change."*

That stopped being true. `accordion` had been **struck** from the package's
roadmap for failing its two-or-more-applications bar; this FAQ and `kerdoos`'s
landing page were the two that cleared it, and the package shipped one in
`v0.5.0`. So:

- The FAQ is `x-ui::accordion`, at `level="h2"` because the page title is the h1
  and the questions are its sections. No styling at the call site — the package
  emits token names and this app already owns the sheet they come from.
- The mobile drawer is inline Alpine. `x-show` is safe there because the drawer
  is a *separate* element from the desktop links; on a shared element an inline
  `display: none` would beat the `lg:` variant and take the desktop nav with it.
- `resources/js/app.js` is now a comment. Flowbite's only real cost was that its
  initialisers bound once on load, so anything replacing DOM afterwards needed
  `initFlowbite()` again — Alpine re-initialises across a Livewire morph, so
  that whole class of bug left with it.
- Constraint bumped to `uclemmer/laravel-ui: ^0.5`.

### And the thing below was skipped, exactly as predicted

The section that follows this one has warned since it was written that a plain
Blade page gets no Livewire assets and therefore no Alpine. **This layout did
not emit `@livewireScripts`, and nobody had noticed**, because Flowbite was
arriving through `@vite` and covering for it. Removing Flowbite without adding
that line would have shipped an FAQ accordion and a hamburger that render
perfectly, pass every markup assertion, and do nothing at all when clicked.

It is now in `resources/views/components/layouts/app.blade.php`, and two tests
in `FrontendWiringTest` hold it there: one reads the layout source, and one
fetches `/faq` and asserts `livewire.js` is in the response. The second exists
because the first would keep passing if the directive were moved somewhere it
never runs.

The lesson is not "read the docs" — the warning was read. It is that a hazard
which fails silently needs a test, not a paragraph.

## What comes next

Nothing in this app blocks on the portal any more, and Flowbite is gone.
Remaining Filament here is `app/Filament/Admin/` and `FairPlugin` on core's
`/admin` panel — steps 3 and 4 of the workspace order, and a later change.
2. **Delete `app/Filament/Rep/` and `RepPanelProvider`**, with a test asserting
   the panel cannot come back unnoticed — the same guard Phase 8 left behind for
   `SitePanelProvider`.

Until step 2, **both `/login` and `/portal/login` work**. A test in
`tests/Feature/Auth/CoreAuthSurfaceTest.php` asserts `/portal` still belongs to
Filament, so finishing the migration cannot quietly leave two login pages
behind.

Filament does **not** leave the application at step 2. `/admin` is still core's
panel, and core still hard-requires `filament/filament`; that is steps 3 and 4
of the workspace order and a later change.

## The thing most likely to be skipped

**Livewire only injects its assets on pages where it renders a component.** A
plain Blade page gets no Alpine, and every interactive laravel-ui component on
it renders inert — with no error. Any layout serving such a page needs
`@livewireScripts`. This cost `saltglass-chartworks` a debugging session; it is
written at the top of laravel-ui's host-integration checklist for that reason.

Do **not** also `import 'alpinejs'`: Livewire's bundled copy plus a direct
import initialises every component twice.
