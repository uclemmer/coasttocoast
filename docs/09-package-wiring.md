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
    { "type": "vcs", "url": "https://github.com/uclemmer/laravel-core.git" }
],
"require": {
    "uclemmer/laravel-core": "^0.1.0"
}
```

The package repositories are **private**. They resolve on the owner's machine because git
credentials are cached there; CI and deploy targets need a deploy key or PAT with read access.

### Upgrading core

1. Tag the release in the `laravel-core` repo.
2. Bump the constraint here and `composer update uclemmer/laravel-core`.
3. Publish any new migrations (below), run them, and run the suite.

This app currently sits on `v0.1.0`, which is behind `main` — notably it predates the extraction of
legal into `uclemmer/laravel-legal`. That lag is the intended consequence of the tagged-release
policy, not a defect.

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

### Open item — the legal migrations

Publishing under `v0.1.0` brings three `core_legal_*` migrations (documents, versions,
acceptances), because that tag predates the legal extraction. They are harmless but this app has no
legal feature. **Decide before the next deploy** whether to drop them from `database/migrations` or
leave them; if core is later bumped past the extraction, they will no longer be published and the
tables would be orphaned.

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

`User::canAccessPanel()` gates the two panels differently:

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
