# 15 — Core `0.5` and the move to `uclemmer/laravel-postmaster`

**Done 2026-08-31.** Core `0.5.0` removed its email log on 2026-08-30. This app takes it from
`uclemmer/laravel-postmaster` instead — `^0.5` and `^0.1`, with a third `vcs` repository entry.

Companion to [14-core-04-upgrade.md](14-core-04-upgrade.md), and the same shape of change: a core
upgrade that is breaking for a reason that has nothing to do with this app's own features.

## Why core gave it up

Core's log ended at `sent` — the transport accepted the message, and nothing more. `EmailLogged`
said exactly that in its own docblock. An application that needed to know whether mail *arrived*
had to keep a second table beside `core_email_logs` and could not join the two.

The replacement owns one widened row: core's columns plus `stream`, `provider`,
`provider_message_id`, `delivered_at`, `bounced_at`, `complained_at`, `first_opened_at` and
`open_count`, with a `postmaster_message_events` child holding provider payloads verbatim.
**Nothing populates those yet** — webhook ingestion is the package's next feature — so per-recipient
status here still means what it meant before. When ingestion lands, this app's campaign delivery
table gets bounce and open data with no schema change on its side, which is the whole point.

## What changed

| Was | Is |
| --- | --- |
| `uclemmer/laravel-core: ^0.4` | `^0.5`, plus `uclemmer/laravel-postmaster: ^0.1` |
| `UClemmer\LaravelCore\EmailLog\EmailLog` | `UClemmer\LaravelPostmaster\Messages\Message` |
| `UClemmer\LaravelCore\Events\EmailLogged` | `UClemmer\LaravelPostmaster\Events\MessageLogged` |
| `core.email_log.*` | `postmaster.enabled`, `postmaster.log.*` |
| `core:prune-email-logs` (03:10 daily) | `postmaster:prune` (03:10 daily) |
| `core_email_logs` | `postmaster_messages` |
| `email-log.view` / `.manage` | `postmaster.view` / `.manage` |
| `core.admin.plugins => []` | one entry: the package's `AdminScreens` |

Three app files carried the integration and all three still do: `MessageRecipient`,
`LinkEmailLogToRecipient`, and `EventServiceProvider`. The listener's logic is unchanged — it still
parses `X-CTC-Recipient-Id` out of the stored headers, and the header round-trip works identically
because the package inherited core's capture pipeline whole.

## Two things that needed thought

**The model is aliased.** This app already has `App\Models\Message` — a campaign. Importing
`UClemmer\LaravelPostmaster\Messages\Message` unaliased would shadow it in `MessageRecipient`, the
one file that needs both. It comes in as `LoggedMessage`.

**The column stays `email_log_id`.** Renaming it to `message_id` would collide with the existing
`message_id` on `message_recipients`, which points at the campaign. Two different "messages" in one
row is exactly the confusion the alias above avoids, and renaming the column would make them
indistinguishable at a glance. The name is now slightly historical; that is the lesser cost.

## The data migration

`2026_08_31_022516_migrate_core_email_logs_to_postmaster.php` copies `core_email_logs` into
`postmaster_messages` and drops the source table. Every core column keeps its name, so it is a copy
rather than a transform, and the added columns are nullable except `open_count`, which defaults to 0.

It runs in **two passes** — rows first, then the self-referencing `resent_from_id` links. A single
`INSERT..SELECT` would only work if rows came back in creation order, which is a property of the
driver rather than of the data.

`down()` **throws**. Core `0.5.0` no longer ships the schema that would have to be recreated, so a
reversal would mean carrying a second copy of core's table definition inside this migration — where
it would be the only copy in existence and would rot. Restore from a backup instead.

The old `create_core_email_logs_table` migration file was **deleted**. It is already recorded as run
in any existing database, so removing the file changes nothing there, and a fresh install now never
creates the table — at which point the copy step no-ops on its `Schema::hasTable` guard.

**It copied zero rows locally**, because the dev database had none. The migration is written for a
database that has some.

## The seam, and what the test now says

`core.admin.plugins` had been empty since 2026-08-21, when the fair's own resources left for
`/staff`. `CoreIntegrationTest` asserted that emptiness. It has one entry now — and it is not the
fair's:

```php
it('contributes only the message log, and gets the rest from core', ...)
it('renders the contributed message log inside the core admin shell', ...)
```

`/admin` looks the same to a user while the message log arrives from a different package. The
fair's own screens are still at `/staff` and still contribute nothing to `/admin`; that half of the
original assertion holds and is pinned by the `users.index` expectation beside it.

The second test is the one that would actually catch a mistake: being registered and rendering are
different failures. It renders inside core's shell because `postmaster.admin.layout` names
`core::admin.layout` — the package cannot hard-code that, since most of its candidate hosts have no
core at all.

## The `@source` line, again

```css
@source '../../vendor/uclemmer/laravel-postmaster/resources/views';
```

Third time this app has needed one (`laravel-ui`, then `laravel-core` in doc 14, now this).
Tailwind 4 skips gitignored directories and `vendor/` is one, so without it the screens render with
a full `class` attribute and no styling, and **nothing errors**. Verified by reading, not by diffing
compiled CSS — worth doing properly before the next deploy.

## The permissions do not carry themselves over

**This is the step that fails silently, and it is not in the data migration above.**

Core's `email-log.view` / `email-log.manage` do not become `postmaster.view` /
`postmaster.manage` on their own. The result: the screen is registered, the route resolves, the
component renders — and the navigation entry never appears for anybody, because nobody holds the
permission it is gated on. No error, no log line.

`core:sync-permissions` is **not** the fix. It creates what the registry declares and prunes what
it does not, so on its own it would delete the two old permissions — **taking every role's grant
with them** — and create two new ones granted to nobody.

`2026_08_31_030000_rename_email_log_permissions_to_postmaster.php` renames the rows instead.
`core_permission_role` links by id, so a rename carries every existing grant across untouched. It
also handles the case where somebody already ran `core:sync-permissions`: the new row exists
alongside the old one, so renaming onto it would violate the unique index — the empty newcomer is
dropped and the one holding the grants is renamed.

Verified here: 21 permissions before, 21 after, coordinator still holding all 21, and the
navigation entry appeared.

## Browser pass — done 2026-08-31

Everything below was checked in a real browser at `https://coasttocoast.test`, signed in as the
seeded coordinator.

- **The `@source` line works.** Diffed the compiled stylesheet before and after adding it, then
  checked every literal `class="…"` token in the package's views against the built CSS. Everything
  the message-log screens render resolves. (Two tokens did not — `bg-surface` and `text-muted` —
  but both are only in the package's *fallback* layout, which this app never renders because it
  sets `postmaster.admin.layout` to `core::admin.layout`. Fixed upstream in postmaster `v0.1.1`.)
- **Capture works end to end.** Submitted the real contact form; both resulting messages — the
  receipt and the fair's alert — were captured, marked `sent`, and listed correctly.
- **The sandbox holds in a live browser**, not just in a unit test: one iframe, `sandbox=""` with
  no allow-\* tokens, `referrerpolicy="no-referrer"`, content in `srcdoc` rather than `src`.
- **No horizontal overflow** on the list screen (`scrollWidth === clientWidth`).

Two defects it found, both fixed upstream and pulled in here:

- The navigation printed the literal word `envelope` beside the link — `AdminScreen::icon()` takes
  inline SVG and renders it raw; it is not an icon name. (postmaster `v0.1.1`)
- **The detail screen returned a 500.** `ViewMessage::mount()` took `$log` while the route segment
  is `{message}`, and route-model binding matches by name — so nothing bound and the view fatalled
  on `$log->status->value`. The index page was fine and every test passed. (postmaster `v0.1.2`)

## Upgrading to postmaster `v0.1.3` (2026-09-01)

The constraint here is `^0.1`, which already admitted `0.1.3` — so the *composer* side was a lock
move and nothing else. The rest was not.

### A version bump can owe you migrations, and it fails at runtime rather than at install

`composer update` succeeded, `composer install` was clean, and then **15 tests failed with
`no such table: postmaster_suppressions`**. This app installed postmaster when it shipped only the
message log, and published exactly the two migrations that existed then. Everything the package has
grown since — suppressions, the ingestion columns, lists, subscribers, memberships — arrived as
`.stub` files nobody had asked for:

```bash
php artisan vendor:publish --tag=postmaster-migrations
php artisan migrate
```

Five published, the two existing ones correctly skipped rather than duplicated.

**The general shape is worth more than the fix.** The workspace file already says a package's
migrations are stubs that must be published; the case it does not cover is the *upgrade*, where you
published once at install and a later version added tables. Nothing about installing the new version
tells you — Composer is satisfied, the app boots, and the failure comes later from whichever code
path first touches the new table. Here that was `PreventSuppressedRecipients`, a listener on
`MessageSending`, so the symptom surfaced in the **Stripe checkout tests**: every one that sends a
confirmation blew up on a suppression lookup, and nothing in the message named postmaster or the
upgrade.

**Publish and migrate after every package version bump, not just at install.**

### What the release actually brings here

Three more admin screens — the do-not-send list, mailing lists, and one list's members with its
consent record — reachable under **Mail** in the admin, gated on the `postmaster.view` this app
already renamed its permissions to on 2026-08-31.

And an `Email permissions` check in `core:doctor`, which came out of a browser pass finding a host
whose permissions had never been synced at all. Run here it reports **OK — registered and present in
the database**, confirming this app's rename migration did its job. That is the check earning its
place immediately: it is the only thing in this family that looks at the real database rather than a
freshly seeded test one.

## Status

`composer test`: **771 passed** (741 before). Browser pass done 2026-08-31; on postmaster `v0.1.3`,
core `v0.5.0`.
