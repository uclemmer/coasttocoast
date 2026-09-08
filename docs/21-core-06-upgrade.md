# 21 — Core `0.5.1` → `0.6.0`, and a `robots.txt` worth having

Upgraded 2026-09-07. The bump itself changed no code: core `0.6.0` adds a route
and a config key and no table, so there is no migration to publish. What the
bump surfaced was worth more than the version — this app had been telling
crawlers to index everything since the day it was installed — and the owner
took the fix the same day.

---

## 1. What was crossed

| Release | Change | Effect here |
| --- | --- | --- |
| `0.6.0` | `GET /robots.txt` served from config, behind `core.robots.enabled` (off by default), plus a `robots.txt` doctor check | **adopted** — see §2 |

Core `0.6.0` is itself a rebuild: the original was tagged on 2026-09-04, lost
unpushed with the machine it was made on, and reconstructed and re-tagged on
2026-09-06. The workspace `CLAUDE.md` section "The 2026-09-06 Machine Move" is
the record.

## 2. The finding: a `robots.txt` that permitted everything

`public/robots.txt` was tracked here and had never been edited since the Laravel
install (`edbe0b4`). It was the framework's stock file, in full:

```
User-agent: *
Disallow:
```

A bare `Disallow:` permits everything. So the fair's **admin at `/admin`**, the
**rep portal**, the **staff screens** and the **password-reset links** were not
kept out of any search index, and neither was anything else.

That was not a leak on its own — every one of those routes is behind a gate, and
the gate is what keeps them private. What was missing is the second line of
defence, and `ckbs` is the worked example of why this workspace decided to care:
its show gallery spent a fortnight public ahead of its show because a condition
was missing from its gate, and there was nothing behind that gate when it
failed.

## 3. What was done

Three steps, and the second is the one that is easy to miss.

1. **`core.robots.enabled` is true** in `config/core.php`.
2. **`public/robots.txt` is deleted.** It had to go: a static file under
   `public/` is answered by the web server before Laravel boots, so leaving it
   would have made the whole config section dead — the route would exist, return
   200 to the test suite, and reach no crawler. The doctor check warns if one
   ever comes back.
3. **The disallow list was drawn from the route table**, not from memory.

### What is served now

```
User-agent: *
Disallow: /admin
Disallow: /portal
Disallow: /staff
Disallow: /email/verify
Disallow: /login
Disallow: /register
Disallow: /forgot-password
Disallow: /reset-password/
Disallow: /storage/
```

`/admin` is **derived** by core from `core.admin.path`; if that path moves, this
follows without anybody editing a list. Everything else is named by hand, and
the reason is worth keeping:

**This app's `core.auth.routes.prefix` is an empty string.** Core's auth *is*
this app's auth, so login lives at `/login` rather than `/core/login`. An empty
prefix would derive `Disallow: /` — which hides the entire site from search —
so core drops it instead. The price of that correctness is that nothing under
the auth prefix is derived either, which is why `/login`, `/register` and
`/forgot-password` are listed explicitly. This app is the first host to
exercise that branch against real config.

The last three lines follow `ckbs`'s categories rather than being invented here:

- **`/reset-password/`** — the URL *is* the credential. An indexed one is a
  working way into somebody else's account, with no gate behind it to refuse
  the visitor, which makes it worse than an indexed private page.
- **`/storage/`** — an uploaded file keeps working at its path after the page
  that showed it closes, so an indexed one outlives the gate in front of it.
  The pages that embed them stay crawlable; only the bare files do not.

### What is deliberately *not* disallowed

The fair's own content: `/`, `/about`, `/events/{event}`, `/faq`, `/sponsors`,
`/representatives`, `/contact`. `RobotsTest` asserts that too, because a rule
broad enough to swallow a public page would otherwise be invisible until the
traffic went.

## 4. The test that keeps it honest

`tests/Feature/Foundation/RobotsTest.php` walks the route table and fails if any
auth-gated GET route is not covered by a rule — so a new private area cannot
ship indexable by omission. It fetches `/robots.txt` **over HTTP** rather than
reading config back, which is the difference from `ckbs`'s version of the same
file: there the document is a static file, here a route builds it, and asserting
on config would prove nothing about what is served.

Two things in it are worth knowing before editing:

- **The static-file assertion is an absence.** The inverse is a real trap —
  `saltglass-chartworks` once carried a test asserting Filament's published
  assets were *present*, which passed happily while defending the very files
  that should have gone.
- **Middleware are matched by exact class name.** Loose matching bites: the
  substring `Authenticate` also matches `RedirectIfAuthenticated`, which marks
  **guest-only** routes — the opposite of gated. That false positive put
  `/register` on the private list while this list was being drawn up, and
  `/register` is a public page. The list above was corrected before anything
  was written to config.

## 5. The config section was spliced in by hand

`config/core.php` here is this app's copy. Re-publishing with `--force` to pick
up the new section would reset **every other value in the file** — not
hypothetical, it is what reset postmaster's master switch on this app during the
`0.6` upgrade and mailed a campaign to somebody who had unsubscribed (doc 20
§3).

## Definition of done

- [x] `uclemmer/laravel-core` `^0.6`, `composer.lock` at `v0.6.0`
- [x] No migration owed — verified, `vendor:publish --tag=core-migrations` copies nothing
- [x] `robots` config section spliced in by hand, with the reasoning in its comment
- [x] `public/robots.txt` deleted, so the route is reachable
- [x] Disallow list drawn from the route table, false positive caught and corrected
- [x] `RobotsTest`: 7 tests — served document, absent static file, every gated route covered, credential URLs, uploads, public pages still crawlable, derived admin path
- [x] Suite green: 994 tests, 960 passed, 0 failed
- [x] `core:doctor` reports **OK robots.txt — Served at /robots.txt, keeping 9 paths out of the index**
- [ ] Confirm on the deployed site that `/robots.txt` serves this and not a cached file
