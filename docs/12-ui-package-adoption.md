# 12 — Adopting `uclemmer/laravel-ui`, and retiring the rep panel

**Status: groundwork complete 2026-08-21. The panel itself is not yet removed.**

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

## What comes next, in order

1. **Auth.** Enable `core.auth.routes.enabled`, wire the app's own registration
   (D9 claim/create) and email verification, and give both a Blade/Livewire
   surface built on laravel-ui.
2. **Portal screens.** Dashboard, registrations list/create/view, grants list,
   organization profile — Livewire full-page components under `/portal`.
3. **Delete `app/Filament/Rep/` and `RepPanelProvider`**, with a test asserting
   the panel cannot come back unnoticed — the same guard Phase 8 left behind for
   `SitePanelProvider`.

Filament does **not** leave the application at step 3. `/admin` is still core's
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
