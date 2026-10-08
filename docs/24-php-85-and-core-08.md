# 24 — PHP `8.5`, core `0.8`, postmaster `0.7`, ui `0.7`

Upgraded 2026-10-07. No application code changed. The bump was forced from
outside, and the part worth reading is §3: five config keys this file had
never received, one of them the admin's own guard.

---

## 1. Why it moved

Every application consuming the package family went to PHP `^8.5` on
2026-10-07 (owner), and the packages followed with minors that require it:

| Package | Was | Now |
| --- | --- | --- |
| `uclemmer/laravel-core` | `^0.7` (`0.7.2`) | `^0.8` (`0.8.0`) |
| `uclemmer/laravel-postmaster` | `^0.6` (`0.6.2`) | `^0.7` (`0.7.0`) |
| `uclemmer/laravel-ui` | `^0.6` (`0.6.0`) | `^0.7` (`0.7.0`) |
| PHP | `^8.4` | `^8.5` |

Narrowing a supported PHP range is breaking, so each package released a
minor, and none of the old constraints admits it. An app left on 8.4 could
take no further package release, fixes included — which is the whole reason
this app moved rather than staying put.

None of the three releases changed code or adds a migration. Core `0.7.3`,
which this jump crosses, is a config key only (§3).

## 2. What changed here

- `composer.json`: the four constraints above. `composer.lock`: those three
  packages, `spatie/laravel-package-tools` by a patch, and the platform entry.
- `CLAUDE.md` / `AGENTS.md`: Boost re-stamped them PHP 8.5.
- Live instructions that named 8.4: [02](02-architecture.md)'s stack and
  constraint tables, [07](07-email-design.md)'s note on postmaster's
  requirements, and [08](08-install-runbook.md)'s prerequisites — including
  `php -v` "must report 8.5.x" and `herd use 8.5`. The roadmap's "switch the
  environment to PHP 8.4" (Phase 0) is a record of what was done then, and
  stays.

**Composer here now has to run under PHP 8.5** (`~/.config/herd/bin/php85`);
`php84` cannot install this lock.

## 3. Five config keys this file never had

`config/core.php` is published and owned here, and core reads every key with a
default, so a key the host never received still works — and the file stops
saying what the package can do. Compared with core `0.8.0`'s by full dotted
path, five were missing:

| Key | From | Why it was missing |
| --- | --- | --- |
| `admin.middleware` | `0.4.0` | This file was published before `0.4`, when the admin was still Filament's |
| `auth.two_factor.code.length` / `.ttl` / `.max_attempts` | `0.7.0` | [22](22-core-07-upgrade.md) wrote in `channels` and `sms_sender` and missed the `code` block |
| `auth.remember` | `0.7.3` | New in this jump |

**`admin.middleware` looked like a hole and was not one.** Core's config merge
is shallow, so this file's `admin` array replaces core's whole block, and
`config('core.admin.middleware')` here was `null`. But core's
`AdminRouteRegistrar` reads it with the same list as its fallback, and
`route:list --path=admin` showed every admin route behind `core.auth` and
`core.permission:admin.access` the whole time. The gap was legibility: the
guard on `/admin` could not be seen where it is configured.

All five are written in by hand at the package defaults, so nothing behaves
differently — not re-published, for the reason [20](20-postmaster-06-upgrade.md)
records. `remember` is inert while two-factor is off here; the comment beside
it says to turn it off if two-factor is ever turned on.

`tests/Feature/Foundation/PublishedConfigTest.php` (copied from
`projects/uclemmer`, which found the same drift the same day) fails if the
published file lacks any key the installed core or postmaster ships; it fails
against the file as it was. A second test asserts the admin guard on the
**routes** themselves, so it holds whichever side supplies it.

## 4. What was verified

998 tests under PHP 8.5: 964 passed, 34 skipped — the same 34 skipped at [22](22-core-07-upgrade.md), plus the three new tests above. Run a second time with deprecations, warnings and notices displayed: none, from this app or from vendor. Pint clean. No source file in this app changed; a version-only bump is indistinguishable from one nobody ran, so the numbers are recorded rather than asserted.

## 5. Left open

`composer audit` reports two `league/commonmark` advisories (one high: a
quadratic-time denial of service in the GFM table extension; one medium: a
raw-HTML filter bypass), affecting `<=2.10.1` and fixed in `2.10.2`. They
predate this change and were not taken here, because this app was not part of
the same day's dependency refresh; `composer update league/commonmark` is the
whole fix.
