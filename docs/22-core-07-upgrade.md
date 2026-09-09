# 22 — Core `0.6.0` → `0.7.1`

Upgraded 2026-09-08. No code changed here. What the bump costs is the two
things a `composer update` never does on its own: **publishing the migration**
the new release added, and **writing the new config keys into a file this app
owns**.

---

## 1. What was crossed

| Release | Change | Effect here |
| --- | --- | --- |
| `0.7.0` | Two-factor delivery channels — an emailed or texted one-time code beside the authenticator app | **not adopted**; `core.auth.two_factor.enabled` is `false` here. The keys are written out anyway, at their defaults — see §3 |
| `0.7.0` | `add_core_two_factor_channels_to_users_table` migration | **published** — see §2 |
| `0.7.0` | `SendsTwoFactorSms` contract; the package ships no implementation | nothing to do; `sms_sender` stays `null`, and the `sms` channel is unavailable until it names a class |
| `0.7.1` | The challenge screen offered no way to ask for the code it was waiting for | not reachable here, since no channel but `app` is enrolled — taken because it is in the same line |

`0.7.0` was ported out of `projects/kerdoos`, whose hand-rolled two-factor had
all three channels; adopting the package there would have been a regression, so
the gap an adoption found was filled in the package. `0.7.1` fixed the screen
that release shipped, found in a browser pass on `projects/duespay` after 711
package tests had passed over it.

## 2. The migration, which nothing tells you about

Core `0.7.0` adds one column, `core_two_factor_channels` on `users`. A host is
never told: `composer update` does not mention a package's migrations, and no
package suite misses one — the package's own testbench creates its schema from
the stubs directly.

```bash
php artisan vendor:publish --tag=core-migrations   # skips the twelve already here
php artisan migrate
```

The publish **skips** rather than duplicates: every migration already published
here keeps its original timestamp, and only the new one arrives. It is a
nullable JSON column, so the migration is additive and its absence would not
have broken anything until somebody turned two-factor on — which is exactly the
delay that makes this class of omission expensive.

## 3. The config keys, added by hand

`config/core.php` is published and owned here, so the new `channels` and
`sms_sender` keys were **written in by hand rather than re-published**. A
`vendor:publish --force` rewrites the whole file, and this app has already paid
for that once: the postmaster `0.2` → `0.6` upgrade re-published its config and
reset the master switch to the package default, taking the send-time
suppression guard down with it (doc 20).

Both values are the package's own defaults, so nothing behaves differently.
They are written out because a reader of this file should be able to see that
the choice exists — a key that is merely absent reads as a capability the
package does not have.

## 4. What was verified

994 tests, 960 passed and 34 skipped, against core `v0.7.1`. No source file in
this app changed, which is the point: a version-only bump is indistinguishable
from one nobody ran, so the number is recorded rather than asserted.

The satellites were not touched. This app takes `postmaster` `^0.6` and `ui`
`^0.6`, neither of which moved in the `0.7.x` wave — postmaster took the core
bump on `main` without a tag, because core is a `require-dev` there.
