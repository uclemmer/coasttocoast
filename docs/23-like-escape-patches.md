# 23 — Core `0.7.2` and postmaster `0.6.2`: the `LIKE` escape, on MySQL

Upgraded 2026-10-06. No application code changed. Two patch releases, and
between them they repair **every search in this app** on the production engine.

---

## 1. What was broken, and why this suite never saw it

Every search here goes through core's `LikeTerm` — nine call sites in `app/`:
registration's organization picker, the roster, and the staff events, FAQ,
grants, interests, messages and organizations screens. Since the 2026-09-02
patch wave (the one that fixed `dana_lee` in `InterestsTest`), `LikeTerm` wrote
`ESCAPE '\'`.

**That is a syntax error on MySQL**, error 1064: MySQL reads a backslash inside
a string literal as an escape of its own, so the quote never closes. This app
runs MySQL in production (docs/02, docs/11). It runs SQLite in development and
in its suite, and SQLite has no such rule — so every search worked here, every
test passed, and every non-empty search would have failed in production.
`projects/derby-days`' CI found it on 2026-10-05, the first MySQL job in the
workspace. Core `0.7.2` escapes with `!`, which means nothing inside a string
literal on any engine.

## 2. Postmaster's message search was broken everywhere, including here

This app registers postmaster's admin screens (`config/core.php` →
`admin.plugins`). Postmaster's message search passed the column `to` unquoted,
and `to` is reserved on SQLite, MySQL and PostgreSQL alike — so `/admin`'s
message log search was a syntax error **on the development database too**, from
the 2026-09-02 patch, whose move to `whereRaw` dropped the quoting, until `0.6.2`. `saltglass-chartworks` found
it walking every admin screen with a search term (its docs/19). `0.6.2` quotes
the columns through the connection's grammar, and also carries `0.6.1`'s
`ESCAPE '!'` change.

## 3. What a `composer update` owed this time

Nothing beyond the update. Neither release adds a migration or a config key —
checked against the tags' `database/` and `config/` trees rather than assumed,
because docs/15, docs/20 and docs/22 each record an upgrade that owed one and
did not say so.

```bash
~/.config/herd/bin/php84/php.exe ~/.config/herd/bin/composer.phar update uclemmer/laravel-core uclemmer/laravel-postmaster
```

The `post-update-cmd` hook ran `boost:update`, which left the guideline stamp at
8.4 and rewrote `boost.json`'s line endings only; that was restored rather than
committed.

## 4. What was verified

`InterestsTest` gains one test: the SQL the interests search runs carries
`ESCAPE '!'` and no backslash. **It is asserted on the string because this
suite cannot run MySQL**, and it was seen red against core `0.7.1` before the
update. 995 tests, 961 passed and 34 skipped, against core `v0.7.2` and
postmaster `v0.6.2` — doc 22's 994/960/34 plus the one above.

**What this cannot prove** is execution on MySQL. Nothing on this machine runs
it; the clause test is the closest a SQLite suite gets.
