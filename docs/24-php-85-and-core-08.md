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

## 5. The commonmark advisories, cleared the same day

`composer audit` reported two `league/commonmark` advisories, affecting
`<=2.10.1`: one high (a quadratic-time denial of service in the GFM table
extension) and one medium (a bypass of `DisallowedRawHtml`, the GFM filter
that neutralises `<script>` and similar tags). They predated this change.
`composer update league/commonmark -w` took it `2.10.0` → `2.10.3` and
`symfony/polyfill-php80` with it, nothing else; `composer audit` is clean.
998 tests, 964 passed and 34 skipped, as above.

**What checking it found, and did not change.** Every Markdown render here is
`Str::markdown()` on staff-written text — the FAQ (public page and the staff
preview) and campaign emails and their staff view. The campaign template's
comment says that converter "escapes raw HTML by default". **It does not.**
Measured on 2.10.3: `<b>`, `<div>`, `<img src=x onerror=…>` and a
`javascript:` link all render live; only the short `DisallowedRawHtml` list
(`<script>` among it) is neutralised. The authors are trusted — FAQ editors,
and coordinators holding `messages.send` — so this is not a public injection
path, but a pasted fragment reaches the public FAQ and recipients' inboxes as
live HTML. Escaping it would change what existing answers render if any use
HTML on purpose, so it was left to the owner.

**Escaped, 2026-10-08 (owner).** All four surfaces now render through one
helper, `App\Support\Markdown::render()` — `Str::markdown()` with
`html_input: escape` and `allow_unsafe_links: false` — so raw HTML shows as
text and a `javascript:` link keeps its words and loses its target, while
Markdown itself (emphasis, lists, links, headings) renders as before. One
helper is also what keeps the editor's preview honest. Before switching, the
content was checked: no seeded FAQ answer and nothing in the dev database used
HTML (the live site's map embed was never transcribed). **Production's FAQ
answers and campaign bodies were not visible from here**; any that used HTML
will now show the tags as text, and an answer that genuinely needs an embed
wants a field of its own rather than a hole in the escaping.
`tests/Feature/Foundation/MarkdownEscapingTest.php` asserts each surface
separately — public FAQ, editor preview, campaign email, staff campaign page —
plus a guard that fails if any class or view calls `Str::markdown()` around
the helper; with the old call sites restored, all five of those fail.
