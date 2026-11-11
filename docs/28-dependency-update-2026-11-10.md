# 28 — Dependency update, 2026-11-10

A routine `composer update -W` and `npm update` inside the existing
constraints. No constraint changed and no application code changed.

---

## 1. What moved

Composer: 32 updates and one new transitive package (`symfony/polyfill-time`).

| Package | Was | Now |
| --- | --- | --- |
| `laravel/framework` | `13.35.0` | `13.42.0` |
| `uclemmer/laravel-core` | `0.8.1` | `0.8.5` |
| `uclemmer/laravel-postmaster` | `0.7.0` | `0.7.1` |
| `uclemmer/laravel-ui` | `0.7.0` | `0.7.2` |
| `laravel/boost` | `2.10.3` | `2.10.4` |
| `laravel/mcp` | `1.0.1` | `1.2.0` |
| `laravel/tinker` | `3.0.2` | `3.1.0` |
| `laravel/pail` | `1.2.7` | `1.3.0` |
| `twilio/sdk` | `8.12.2` | `8.14.0` |
| `stripe/stripe-php` | `21.3.2` | `21.3.3` |
| `guzzlehttp/guzzle` | `8.2.0` | `8.3.1` |
| `nesbot/carbon` | `3.14.2` | `3.15.1` |

**Not taken, because they are outside the constraints:**
- `stripe/stripe-php` `22` is a major version.
- `uclemmer/laravel-core` `0.9` is a minor version, and moving to it is breaking. The
  workspace keeps every app on core `^0.8` until each one is raised on its own.

**The three `uclemmer/*` patches owe nothing.** Their changelogs list no
migration and no config key:
- core `0.8.2`–`0.8.4` make `core.admin.logo` and `favicon` work again and send a
  user back to their destination after two-factor setup.
- core `0.8.5` and postmaster `0.7.1` only raise dependency floors, to Livewire `^4.4.8`
  and a `conflict` on the vulnerable Symfony releases.
- ui `0.7.2` stops `:required="false"` starring a field's label.

npm, lock only:
- `vite` `8.3.4` → `8.3.7`
- `tailwindcss` and `@tailwindcss/vite` `4.3.3` → `4.3.5`
- `laravel-vite-plugin` `3.2.0` → `3.2.1`
- `rolldown` → `1.2.18`, `postcss` → `8.5.32`

Vite's own copy of `lightningcss` was deduplicated away. `concurrently` `10` is
a major version and was not taken.

## 2. Boost regenerated the guidelines and skills

Composer's `post-update-cmd` did not run, because the first install failed
(see §3). So `boost:update` was run by hand, the way the workspace prescribes:
`HOME='C:/Users/uriah' APP_URL=https://coasttocoast.test`. Boost `2.10.4`
rewrote the skill files under `.claude/skills` and `.github/skills` and dropped
one line, "ask the user to run the complete suite", from `CLAUDE.md` / `AGENTS.md`.
These are kept as generated. The pair is still identical and still says PHP 8.5,
and `AgentGuidelinesTest` holds that.

## 3. A Composer download failure

The first install from the new lock failed on every package, public ones
included, with "Could not authenticate against github.com". The API quota was
fine: 4,994 of 5,000 left. The cause was the GitHub token in Composer's global
config. GitHub rejected it, so Composer could not download even public dists.
Replacing it with the GitHub CLI's token fixed it:
`composer config --global github-oauth.github.com (gh auth token)`.
`composer diagnose` then reported `github.com oauth access: OK`.
**A stale global Composer token breaks every package, not only the private
`uclemmer/*` ones.** Read the error as "token rejected" once the quota checks out.

## 4. Verification

| Check | Before | After |
| --- | --- | --- |
| Pest | 977 passed, 34 skipped | 977 passed, 34 skipped |
| Pint `--test` | — | clean |
| `composer audit` | — | no advisories |
| `npm audit` | 0 vulnerabilities | 0 vulnerabilities |
| `npm run build` | — | clean |

Run on SQLite locally; CI (doc 25) covers MySQL 8.4 and MariaDB 11. The
baseline run showed one extra failure, `ExampleTest`. It came only from running
before the first `npm run build` in this worktree (Vite manifest missing), and
the test passes once the build exists.
