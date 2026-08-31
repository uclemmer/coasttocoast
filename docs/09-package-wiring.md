# 09 — Package Wiring & Upgrades

How this app consumes `uclemmer/laravel-core`, why it is wired the way it is, and what to do when
the package moves. Written 2026-08-16, when the app was converted from a broken path repository to
a tagged release.

## Consumption model

Coast to Coast is a **production app**, so it consumes packages as **tagged releases over VCS** —
never as a path repository and never as `dev-main` or `@dev`. Path repositories are reserved for
`saltglass-chartworks`, the demo/development harness for the package family.

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/uclemmer/laravel-core.git" },
    { "type": "vcs", "url": "https://github.com/uclemmer/laravel-ui.git" }
],
"require": {
    "uclemmer/laravel-core": "^0.5",
    "uclemmer/laravel-postmaster": "^0.1",
    "uclemmer/laravel-ui": "^0.6"
}
```

The package repositories are **private**. They resolve on the owner's machine because git
credentials are cached there; CI and deploy targets need a deploy key or PAT with read access.

### Upgrading core

1. Tag the release in the `laravel-core` repo.
2. Bump the constraint here and `composer update uclemmer/laravel-core`.
3. Publish any new migrations (below), run them, and run the suite.

This app sits on **core `^0.5`** (`v0.5.0`) and **postmaster `^0.1`** as of 2026-08-31 — see
[15-core-05-and-postmaster.md](15-core-05-and-postmaster.md), and
[14-core-04-upgrade.md](14-core-04-upgrade.md) for the previous one. Note that `^0.5`
deliberately does not admit a future `0.5.0`: under SemVer 0.x each minor is treated as breaking, so
moving to the next core release is always an explicit decision here, never a drift. That rule is
what made the 0.2 → 0.4 jump a considered change rather than something that happened during a
routine `composer update`.

**The laravel-ui vcs repository above is not optional.** Core requires `uclemmer/laravel-ui`, and
Composer does not inherit repository definitions from dependencies — without that second entry,
**core itself will not resolve**. Every consumer of any `uclemmer/*` package needs it.

## Publishing migrations — the part that is easy to miss

Core ships its migrations as `.stub` files. Installing the package is **not** enough; nothing
creates `core_roles`, `core_permissions`, etc. until you publish them:

```bash
php artisan vendor:publish --tag=core-migrations
```

Note the tag is `core-migrations`, **not** `laravel-core-migrations`.
`spatie/laravel-package-tools` derives publish tags from the package's *short* name, stripping the
`laravel-` prefix — which is also why the published config file is `config/core.php` rather than
`config/laravel-core.php`. Asking for `laravel-core-migrations` returns "No publishable resources"
and reads like the package is broken when it is not.

The two hand-registered tags in core's service provider are the exception and do use the long
form: `laravel-core-theme` and `laravel-core-legal-stubs`.

### The legal migrations — settled 2026-08-17

Publishing under core `v0.1.0` brought three `core_legal_*` migrations (documents, versions,
acceptances), because that tag predated the legal extraction. **They have been deleted.** Nothing in
this app referenced legal — no config, no routes, no code, no tests — so the tables were pure
residue from a feature that now lives in `uclemmer/laravel-legal`.

If this app ever does want versioned legal documents, the answer is to require that package rather
than to resurrect these files: it owns `legal_documents`, `legal_versions` and `legal_acceptances`
under its own prefix, and requires core `^0.5` exactly as this app now does.

**If a database has already run them**, dropping the migration files does not drop the tables. A
local or staging database migrated before 2026-08-17 still carries three empty `core_legal_*`
tables, and no future migration will remove them. They are inert, but remove them by hand if you
care about a clean schema.

## Test bootstrap — permissions must be synced

`RefreshDatabase` migrates but seeds nothing, so `core_permissions` starts empty. Core's
`Role::givePermissionTo()` resolves permission *names* against that table and **silently drops any
that do not resolve** — no exception, no warning. A factory that grants `admin.access` against an
empty table therefore produces a role holding no permissions, and the panel tests fail as 403s that
look exactly like an authorization bug.

`tests/Pest.php` runs the sync that deploys run, so the suite mirrors production:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Artisan::call('core:sync-permissions'))
    ->in('Feature');
```

If panel tests start returning 403 for a user who should have access, check that this is still in
place before suspecting the gate.

## `canAccessPanel` and email verification

> **Superseded 2026-08-22.** `User::canAccessPanel()` no longer exists. Filament asked the model
> whether it could enter a panel; there are no panels — the rep portal became Livewire on
> 2026-08-21 (doc 12) and core's admin followed in core 0.4 (doc 14). `/admin` is gated by route
> middleware (`core.permission:admin.access`) and `/portal` by the app's own auth plus the
> `verified:` middleware.
>
> The section is kept because **the reasoning below is still the reasoning**, and the trap it
> records is the kind that comes back: an eager gate that runs *before* the middleware which would
> have redirected the user turns a verification prompt into a dead-end 403. Whatever replaces a
> gate, check what runs first.

`User::canAccessPanel()` gated the two panels differently:

- `core` (admin) — `Gate::forUser($this)->allows(AdminPermissions::ACCESS)`. There is no `is_admin`
  column anywhere; access comes from the permission.
- `rep` (portal) — `true`.

The rep panel returns `true` deliberately. `RepPanelProvider` calls `->emailVerification()`, which
sets `requiresEmailVerification(true)`, and Filament then guards the panel's page routes with its
`verified:` middleware. **That middleware is what enforces verification**, and it redirects
unverified users to the verification prompt.

An earlier `'rep' => $this->hasVerifiedEmail()` was redundant with that middleware and actively
harmful: `Filament\Http\Middleware\Authenticate` calls `canAccessPanel()` and `abort(403)`s on
false, and it runs *first* — so unverified users got a dead-end 403 instead of the prompt they were
sent to collect. Removing it loosened nothing; verification is still enforced one layer down.

When card 3.0 lands membership rules (pending / active / retired), that is the check that belongs
in the `rep` arm — not email verification.

## Change log

| Date | Change |
| --- | --- |
| 2026-08-16 | Converted from a broken path repo (`../laravel-core`) to vcs + `^0.1.0`. Filament v5.7.6 arrived transitively via core. Published 16 core migrations. Added the permission sync to `tests/Pest.php`. Removed the redundant `hasVerifiedEmail()` check from `canAccessPanel()`. Suite went from not booting at all to 32/32. |
| 2026-08-16 | Workspace-wide dependency alignment: PHP was already `^8.4`; `livewire/livewire: ^4.3` added as a direct dependency (v4.4.0, previously arriving only transitively through Filament). |
| 2026-08-16 | Added `it('leaves email verification to the route middleware, not the panel gate')` to `RepPanelAccessTest`, pinning that `canAccessPanel('rep')` stays open while the `verified:` middleware does the blocking. Verified by mutation: restoring `hasVerifiedEmail()` fails the new test on the gate assertion. Suite 33/33. |
| 2026-08-17 | Upgraded core `^0.1.0` → `^0.2` (`v0.2.0`), the release that extracted legal. Deleted the three orphaned `core_legal_*` migrations. No new migrations to publish — core `v0.2.0` ships the same thirteen this app already has. Suite 33/33. |
| 2026-08-17 | Installed Laravel Boost. `boost.json` was missing its `agents` key, so no `CLAUDE.md`/`AGENTS.md`/`.mcp.json` had ever been generated here and `boost:update` failed on every `composer update`. This could not be fixed before now: the app did not boot until core was installed, and `boost:install` needs a bootable app. |
| 2026-08-22 | Upgraded core `^0.2` → `^0.4` and ui `^0.5` → `^0.6` in the workspace tag wave, which took `filament/filament` out of the lock entirely. Nothing in `app/` used Filament; eleven files still *declared* they did (nine enums' `HasColor`/`HasLabel`, `User`'s `FilamentUser` and `canAccessPanel()`) and would have fatalled at class load. Four test files ported off the Filament facade, `@source` added for core's views, two retired config keys removed. Suite 739 → 740. Full record in [14-core-04-upgrade.md](14-core-04-upgrade.md). |
| 2026-08-31 | Upgraded core `^0.4` → `^0.5` and added `uclemmer/laravel-postmaster` `^0.1` with a third `vcs` entry. Core 0.5 removed its email log; the message log comes from the package now. Three app files re-pointed (`MessageRecipient`, `LinkEmailLogToRecipient`, `EventServiceProvider`), the model aliased as `LoggedMessage` around this app's own `Message`, a two-pass data migration into `postmaster_messages`, `core.admin.plugins` gaining its first entry, and a third `@source` line. Suite 740 → 741. Full record in [15-core-05-and-postmaster.md](15-core-05-and-postmaster.md). |
| 2026-08-31 | Browser pass on the migrated admin. Found that core's `email-log.*` permissions do not carry over — the screen registers, the route resolves, and the nav entry appears for nobody; fixed by a **rename** migration, since `core:sync-permissions` would prune the old rows and take every grant with them. Also raised postmaster `v0.1.0` → `v0.1.2` for two defects the pass found: a literal `envelope` printed in the nav, and a 500 on the detail screen from a route-binding parameter name. See [15-core-05-and-postmaster.md](15-core-05-and-postmaster.md). |
