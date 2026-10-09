# 26 — Dependency update, 2026-10-09

A routine `composer update -W` and `npm update` inside the existing
constraints. No constraint changed, no `uclemmer/*` package moved, and no
application code changed. One test was rewritten because Livewire changed how
its test harness reports a missing model.

---

## 1. What moved

Composer: 58 updates and one removal (`thecodingmachine/safe`, no longer
required by anything).

| Package | Was | Now |
| --- | --- | --- |
| `laravel/framework` | `13.30.1` | `13.35.0` |
| `livewire/livewire` | `4.4.3` | `4.4.7` |
| `pestphp/pest` | `5.1.3` | `5.3.1` |
| `phpunit/phpunit` | `13.3.1` | `13.4.1` |
| `laravel/boost` | `2.7.0` | `2.10.3` |
| `laravel/mcp` | `0.9.4` | `1.0.1` |
| `laravel/pint` | `1.30.5` | `1.32.1` |
| `brick/math` | `0.18.0` | `1.0.0` (transitive) |
| `stripe/stripe-php` | `21.3.1` | `21.3.2` |
| `twilio/sdk` | `8.12.0` | `8.12.2` |

`uclemmer/laravel-core` `0.8.1`, `laravel-postmaster` `0.7.0` and
`laravel-ui` `0.7.0` are unchanged, so no migration or config key is owed.

npm: lock-only — `vite` `8.2.2` → `8.3.4`, `rolldown` `1.2.6` → `1.2.13`,
`concurrently` `9.2.5`, `postcss` `8.5.29`, and `shell-quote` `1.9.0` →
`1.12.0`, which clears the advisory the workspace's other apps needed an
`overrides` entry for. `npm audit`: 0 vulnerabilities. `npm run build` is clean.

## 2. The one regression: a missing model is a 404 now, not an exception

`PortalTest` "refuses another organization's registration" expected
`livewire(ShowRegistration::class, …)` to **throw**
`ModelNotFoundException`, which `mount()` raises through its scoped
`findOrFail()`. Between Livewire `4.4.3` and `4.4.7` the test harness
(`SupportTesting\RequestBroker`) began excluding `ModelNotFoundException`,
`HttpException` and `AuthorizationException` from `withoutExceptionHandling()`,
so they are rendered as the response a browser would get instead of escaping
the call.

The behaviour under test did not change — the scoped query still says the
record does not exist, which is the point (no "exists, but forbidden" oracle).
The test now asserts `assertStatus(404)` and that the other organization's rep
name does not appear on the page; the sibling test above it asserts the rep
name *does* appear on the viewer's own registration, so the negative is known
to be looking at something real.

## 3. Boost regenerated the guidelines and skills

`laravel/boost` `2.10` rewrote `CLAUDE.md` / `AGENTS.md` (still identical,
still "running on PHP 8.5") and the skill files under `.claude/skills` and
`.github/skills`. Kept as generated. Note one wording change: Boost's
"Test Enforcement" block now says copy- and layout-only changes need no test.
That is Boost's default, not this project's rule — golden rule 6 in
`README.md` (every task ships with tests and a docs update) still governs.

## 4. Verification

| Check | Before | After |
| --- | --- | --- |
| Pest | 970 passed, 34 skipped | 970 passed, 34 skipped (1 failed before the fix in §2) |
| Pint `--test` | — | clean |
| `composer audit` | — | no advisories |
| `npm audit` | — | 0 vulnerabilities |

Run on SQLite locally; CI (doc 25) covers MySQL 8.4 and MariaDB 11.
