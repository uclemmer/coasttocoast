# 21 — Core `0.5.1` → `0.6.0`

Upgraded 2026-09-07. One release, one new feature, and **no code changed here**
— the constraint moved and a config section was added. There is no migration to
publish: core `0.6.0` adds a route and a config key, and no table.

---

## 1. What was crossed

| Release | Change | Effect here |
| --- | --- | --- |
| `0.6.0` | `GET /robots.txt` served from config, behind `core.robots.enabled` (off by default), plus a `robots.txt` doctor check | none until switched on — and it is **not** switched on, see §2 |

Core `0.6.0` is itself a rebuild: the original was tagged on 2026-09-04, lost
unpushed with the machine it was made on, and reconstructed and re-tagged on
2026-09-06. The workspace `CLAUDE.md` section "The 2026-09-06 Machine Move" is
the record. 987 tests here pass against it (953 passed, the rest skipped), which
is what a version-only bump is supposed to demonstrate.

## 2. The finding: this app has a `robots.txt` that permits everything

**Left as a decision for the owner rather than made during a version bump**, but
it is the reason this doc is longer than the table above.

`public/robots.txt` is tracked here and has never been edited since the Laravel
install (`edbe0b4`). It is the framework's stock file, in full:

```
User-agent: *
Disallow:
```

A bare `Disallow:` permits everything. So the fair's **admin at `/admin`** is
not kept out of any search index, and neither is anything else. That is not a
leak on its own — every one of those routes is behind a gate, and the gate is
what keeps them private. It is the second line of defence that is missing, and
`ckbs` is the worked example of why the workspace decided to care: its show
gallery spent a fortnight public ahead of its show because a condition was
missing from its gate, and there was nothing behind that gate when it failed.

Core `0.6.0` exists to close exactly this, and it will not fight the file. **A
static `public/robots.txt` wins**: every web server answers it before Laravel
boots, so switching the flag on without deleting the file changes nothing that
a crawler can see. The new doctor check reports that state as a warning; with
the flag off, as it is here, it reports:

```
  SKIP  robots.txt
        core.robots.enabled is false.
```

### What switching over would produce, measured

Not guessed — `Robots::disallows()` was called against this application's real
config on 2026-09-07 and returned:

```php
['/admin']
```

One line, and the interesting half is what is **absent**. This app sets
`core.auth.routes.prefix` to an **empty string**, because core's auth *is* this
app's auth and login lives at `/login` rather than `/core/login`. An empty
prefix would derive `Disallow: /`, which hides the entire site from search —
core drops it instead, deliberately, and this host is the first place that
branch has been exercised against real config.

So the switch is three steps, not one, and the second is the one that is easy
to miss:

1. Set `core.robots.enabled` to true in `config/core.php`.
2. **Delete `public/robots.txt`.** Until it goes, the route is unreachable.
3. Name anything else that should stay out of the index in
   `core.robots.disallow` — the auth pages among them, since the empty prefix
   contributes nothing. `/portal` is a candidate; the public fair pages,
   `/contact` and the sponsor pages are not, and should stay crawlable.

## 3. The config section was spliced in by hand

`config/core.php` here is this app's copy. Re-publishing with `--force` to pick
up the new section would reset **every other value in the file** — which is not
hypothetical, it is what happened to postmaster's master switch on this very app
during the `0.6` upgrade and mailed a campaign to somebody who had unsubscribed
(doc 20 §3). The block was added by hand, with the reasoning above written into
its comment so the next person reading the config finds the decision rather than
re-deriving it.

## Definition of done

- [x] `uclemmer/laravel-core` `^0.6`, `composer.lock` updated to `v0.6.0`
- [x] No migration owed — verified, `vendor:publish --tag=core-migrations` copies nothing
- [x] `robots` config section spliced in, off, with the reasoning in its comment
- [x] Suite green: 987 tests, 953 passed, 0 failed
- [x] `core:doctor` reports the new check
- [ ] **Owner decision: adopt the served `robots.txt` and delete the static one?** §2
