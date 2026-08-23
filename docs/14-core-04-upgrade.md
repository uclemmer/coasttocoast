# 14 — Upgrading to laravel-core 0.4: the last of Filament

**2026-08-22.** This app moved from `uclemmer/laravel-core: ^0.2` to `^0.4` (and
`uclemmer/laravel-ui: ^0.5` to `^0.6`) as part of the workspace tag wave that
closed the Filament removal. `filament/filament` is no longer in the lock file,
and `vendor/filament/` does not exist.

Two minors in one step, and only one of them mattered. Core `0.3.0` was the
Laravel 13 narrowing, which this app already satisfied (`laravel/framework:
^13.8`). Core `0.4.0` is the one that removed the Filament panel.

## What actually changed here

**`/admin` is still `/admin`.** It was core's prebuilt Filament panel; it is
core's Livewire screens on `uclemmer/laravel-ui`. Same path, same
`admin.access` gate, same nine modules in `core.admin.modules`, same brand.
`core.admin.plugins` is the same config key — it holds `ProvidesAdminScreens`
class-strings rather than Filament plugins, and this app names none.

**The fair's own screens were already gone.** Doc 13 moved them to `/staff` on
2026-08-21 and doc 12 moved the rep portal on the same day. That is why this
upgrade was small: by the time core dropped Filament, nothing in `app/` was
building on it.

## What broke, and it was all vestigial

Nothing in this app *used* Filament any more. Eleven files still **declared**
that they did, and every one of them would have fatalled at class load the
moment the package left the lock:

| File(s) | What it declared | Why it was there |
| --- | --- | --- |
| Nine `app/Enums/*.php` | `implements HasColor, HasLabel` / `HasDescription` | Filament rendered enums through these contracts |
| `app/Models/User.php` | `implements FilamentUser`, `canAccessPanel(Panel)` | Filament asked the model whether it could enter a panel |

The **methods** those interfaces required are used heavily — `getLabel()` is
called from Livewire components, Blade views and a notification listener — so
the fix was to drop the `implements` clauses and the imports and keep the
bodies. Only the declarations were Filament's.

`User::canAccessPanel()` went entirely. It answered for two panel ids (`core`
and `rep`), neither of which had existed since 2026-08-21 — the method was dead
and the interface was keeping it compiling. The question it was really asking is
the Gate's, and `AdminPanelAccessTest` asks the Gate directly now.

**`getColor()` on nine enums is now uncalled.** Nothing reads it: the staff
screens build their own badge variants with `match` expressions. It is kept
rather than deleted because it is nine correct methods that a badge column will
want, and deleting them on the strength of one grep is the kind of tidy-up that
gets reverted. Recorded here so the next person does not have to re-derive it.

## The four tests that had to be rewritten

All four asserted through Filament's facade. None of their **claims** changed —
each is now asked of the thing that replaced the panel.

- `CoreIntegrationTest` — "registers the admin panel, and only that one" is
  `Admin::enabled()` + `Admin::path()`; "no longer attaches a fair plugin" is
  `config('core.admin.plugins') === []` plus two `Admin::has()` calls proving
  core's own screens arrived; branding reads the config key the layout reads.
- `ContentResourcesTest` — `ContentResource::canAccess()` became a real request
  to `Admin::url('content.index')`. That is a better test than it was: core 0.4
  gates a screen with route middleware **and** the component's own `mount()`,
  and only a request exercises both.
- `AdminPanelAccessTest` — `/admin/login` was Filament's. There is one login
  page in this app, at `/login`, which is the same consolidation doc 12
  described for the rep portal. The guest redirect assertion moved with it, and
  a new test pins that `/admin/login` is a 404 rather than leaving the old URL
  untested.
- `tests/Pest.php` — `usingAdminPanel()` and `usingSitePanel()` set Filament's
  current panel so a Livewire test could mount a Filament page. Deleted;
  `livewire()` needs no ceremony.

## The host obligation that fails silently

`resources/css/app.css` gained a line:

```css
@source '../../vendor/uclemmer/laravel-core/resources/views';
```

This is **new with core 0.4 and it is the trap worth knowing.** Under Filament,
`/admin` was styled by a stylesheet the panel compiled for itself. It is Blade
in the package now, and `vendor/` is gitignored, so Tailwind's automatic scan
skips it — without this line every screen at `/admin` renders with a full
`class` attribute and no styling, and nothing reports an error. It is the same
line and the same reasoning as the `laravel-ui` one directly above it.

### Verified by diffing the compiled stylesheet, and the diff was surprising

Per the workspace rule — read the built CSS, not the page. Removing the line and
rebuilding changed the stylesheet by **82 bytes and exactly one class**:
`text-gray-950`.

Everything else core's admin views use is already covered, because they are
built from `laravel-ui` components and this app scans that package's views
already. So the line currently earns almost nothing.

**Keep it anyway.** It is insurance against core's own Blade using a utility
`laravel-ui`'s views do not, which would then silently not compile. The point of
the line is the class it will one day contribute, not the one it contributes
today.

### The one class is a comment, and that is a real (small) defect

`text-gray-950` appears nowhere in core's markup. It appears in a **Blade
comment** in `vendor/uclemmer/laravel-core/resources/views/admin/brand.blade.php`,
in prose explaining that the class was removed:

> it carried `dark:text-white` and Filament's `text-gray-950`. Neither survives
> the move.

Tailwind's scanner is a plain text scanner. It does not parse Blade, so it lifts
the class name out of the explanation and compiles a rule for it. Core's fix is
correct in the markup; the comment resurrects the utility in every host that
scans core's views.

One dead rule, so nothing is broken. Two things follow:

1. **Naming a utility class verbatim in a scanned file adds it to the build**,
   comment or not. Worth remembering when writing this kind of explanation.
2. It is noise in exactly the check the workspace guide tells you to trust. A
   diff of one class that turns out to be prose is a cheap lesson; a diff of
   forty would have been read as "the line is working".

Candidate for core `v0.4.1`. Not fixed here, because re-tagging a package
published minutes earlier to remove one unused CSS rule is the wrong trade.

## Config keys that went

`core.admin.colors` and `core.admin.vite_theme` were removed in core 0.4 and are
gone from `config/core.php` with it. Both were Filament's — a hex string
expanded into a Filament palette, and a path to a compiled Filament theme. The
admin takes its colours from `laravel-ui`'s design tokens now, which this app's
stylesheet already imports.

`core.admin.enabled`, `path`, `brand`, `logo`, `logo_height`, `favicon`,
`plugins`, `modules` and `widget_providers` are all unchanged.

## Definition of done

- [x] `composer.json` on core `^0.4`, ui `^0.6`; `filament/*` gone from the lock
- [x] Nine enums and `User` free of Filament declarations, methods kept
- [x] Four test files ported — every claim preserved, none deleted
- [x] `@source` for core's views added, and verified by diffing the built CSS
- [x] Retired config keys removed with a note saying what they were
- [x] **740 tests passing** (739 before; `ContentResourcesTest` gained one),
      Pint clean
- [ ] `/admin` driven in a browser by a human. The suite proves the routes, the
      gate and the screens mount; it cannot prove the shell looks right, and
      this is the first time this app has rendered that admin from its own
      stylesheet rather than Filament's.
