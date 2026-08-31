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

## Status

`composer test`: **741 passed** (740 before; the seam test split into two). No browser pass.
