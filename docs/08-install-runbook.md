# 08 — Install runbook (card 1.1)

> **Purpose:** the exact commands to bring a checkout of this app up on Laravel Herd, and what each one is
> for. Written 2026-08-16 with card 1.1. Anything in here that turns out to be wrong should be fixed in
> place — this file is the record of what actually works, not what was planned.

## Prerequisites

- **PHP 8.4** — `uclemmer/laravel-core` requires `^8.4`, and `composer.json` now pins the app to it too.
  In Herd: *Settings → PHP → 8.4*, then set this site to 8.4 (`herd use 8.4` inside the project also works).
- The sibling package repo at `C:\Users\uriah\Herd\laravel-core` — `composer.json` references it as a
  **path repository** (`../laravel-core`, symlinked), so edits to the package are live here with no
  re-install. Deployment swaps this for a VCS/Packagist entry.

## First install

```bash
cd C:\Users\uriah\Herd\coasttocoastcollegefair

php -v                          # must report 8.4.x before anything else

composer update                 # resolves uclemmer/laravel-core
```

`composer.json` requires `uclemmer/laravel-core: "@dev"` — a path repository has no released version, so the
constraint has to accept a dev one. `composer require` is not needed; the entry is already written.

### Publish the package's migrations

`config/core.php` is **already published and edited** (see below) — do not re-publish it or you will lose the
fair's settings. The migrations still need publishing, because they ship as `.php.stub` files:

```bash
php artisan vendor:publish --tag=laravel-core-migrations
php artisan migrate
```

That creates `core_roles`, `core_permissions`, `core_role_user`, `core_permission_role`, `core_email_logs`,
`core_job_metrics`, `core_contents`, `core_content_revisions`, `core_contact_submissions`, `core_profiles`,
`core_settings`, plus this app's `phone` / `sms_opt_in` columns on `users`.

> If the publish tag differs from the name above, list what is available with
> `php artisan vendor:publish --provider="UClemmer\LaravelCore\LaravelCoreServiceProvider"` and record the
> real tag here.

### Link the public disk

```bash
php artisan storage:link
```

Organization and sponsor logos are read straight from `public/storage` and render as broken images
without it. FAQ attachments — the signed W-9 — are on the *private* disk and streamed by a
controller, so they need no link (doc 10, D-9-c).

> **This section used to be "Publish Filament's assets", and the history is worth keeping.**
> Filament arrived *transitively* through `uclemmer/laravel-core`, so its installer never ran here
> and nothing copied its CSS and JS into `public/`. Every page served a 200 and rendered as unstyled
> HTML with `/css/filament/...` 404ing — the failure doc 10 D-8-a records. The fix was
> `filament:assets` plus a `filament:upgrade` hook in `post-autoload-dump`.
>
> **Both are gone as of 2026-08-19, and the hook had become a defect.** Filament left this
> application with the `/staff` rebuild (docs/13) and the core `0.4` upgrade (docs/14), but the hook
> outlived it: `artisan filament:upgrade` exits 1 with "There are no commands defined in the filament
> namespace", which fails `post-autoload-dump` and therefore **`composer install` itself** on any
> fresh clone or deploy. It passed locally only because `vendor/` was already built. The published
> assets under `public/css/filament`, `public/js/filament` and `public/fonts/filament` went with it —
> 37 tracked files for a framework that is not installed. `FrontendWiringTest` now asserts the
> absence rather than the presence.

### Permissions, roles and a coordinator account

```bash
php artisan core:sync-permissions      # upserts core's permissions + App\Support\Permissions
php artisan db:seed --class=Database\\Seeders\\RoleSeeder
```

`RoleSeeder` runs `core:sync-permissions` itself, so the first command is only for when you want to see the
counts. Then grant yourself the role in `tinker`:

```php
$u = App\Models\User::factory()->create(['name' => 'Matt', 'email' => 'you@example.com']);
$u->assignRole('coordinator');
```

(Real coordinator seeding — a named account with a known password — lands with card 1.3.)

### Check it

```bash
php artisan core:doctor      # exits non-zero on a jointly-wrong configuration
php artisan test
vendor/bin/pint
```

Then browse:

| URL | What it should be |
|---|---|
| `http://coasttocoastcollegefair.test/admin` | laravel-core's panel, branded "Coast to Coast College Fair"; 403 unless you hold `admin.access` |
| `http://coasttocoastcollegefair.test/portal` | the rep portal — login / register / forgot password, verification required |

## What card 1.1 configured

`config/core.php` is a **copy of the package's config**, published by hand and edited. The deltas from the
package defaults:

| Key | Value | Why |
|---|---|---|
| `admin.enabled` | `true` | use the prebuilt panel rather than building one (D6) |
| `admin.path` / `admin.brand` | `admin` / `Coast to Coast College Fair` | |
| `admin.plugins` | `[App\Filament\FairPlugin::class]` | the seam our resources attach through |
| `email_log.enabled` | `true` (**READ AT BOOT** — restart queue workers after changing) | every send is logged (doc 07) |
| `email_log.prune_after_days` | `400` | cross-year campaigns need more than 13 months of history |
| `content.enabled` | `true` | Home/About copy lives in content blocks (doc 00) |
| `contact.enabled`, `contact.routes.enabled` | `true` | the public contact form (card 5.4) |
| `contact.routes.page` | `false` | we render our own Contact page and embed `<x-core::contact-form />` |
| `contact.recipients` | `[env('ADMIN_ALERT_EMAIL')]` | the coordinator's inbox |
| `queue.enabled` | `true` | job metrics + failed-job tooling in the panel |
| `profile.enabled` | `true` | enables the panel's settings module |
| `permission_providers` | `[App\Support\Permissions::class]` | our permissions get synced with core's |

When the package's config gains keys, publish a scratch copy with `--force` into a temp directory and diff it
against ours rather than overwriting.

## Deviation from the card

Card 1.1 says to apply laravel-core's `CanAccessCorePanel` trait to `User`. We do **not**: that trait answers
`canAccessPanel()` for *every* panel, and this app has two — applying it would lock reps out of `/portal`.
`User::canAccessPanel()` implements the same `admin.access` Gate check for the `core` panel and requires a
verified email for the `rep` panel. Behaviour on `/admin` is unchanged, including the super-admin bypass.
