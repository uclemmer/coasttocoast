# 20 — Postmaster `0.2` → `0.6`

Upgraded 2026-09-04. Four minors in one step, and the code needed no changes at
all — everything that cost anything was configuration.

---

## 1. What was crossed, and why none of it bit

| Release | Change | Effect here |
| --- | --- | --- |
| `0.3.0` | `MailEventType::Reactivated` | none — ingestion is off |
| `0.4.0` | **BREAKING**: `ListMailable::forMembership()` gained a second argument | none — this app implements no `ListMailable` |
| `0.4.1` | `LIKE` escaping fix in the admin search | inherited |
| `0.5.0` | `confirmation_ip`, `postmaster:prune-memberships`, `ConfirmSubscription::apply()` | one migration to publish |
| `0.5.1` | the prune deletes an orphaned address too | inherited |
| `0.6.0` | an unrecognised Postmark bounce type is **soft**, not hard | none — ingestion is off |

**The breaking change was checked rather than assumed.** `0.4.0` makes an
old one-argument `forMembership()` a fatal at class load, which is loud — but
loud at boot is still a broken deploy, so `grep -rn 'ListMailable\|forMembership' app/`
came first. This app uses the message log and the suppression list; it has no
mailing list and no broadcast, so it implements nothing.

**`0.6.0` reaches nothing here.** The bounce policy only applies with
`ingestion.enabled` true and the Postmark driver, and this app receives no
provider webhooks. The published config predated the ingestion block entirely,
so it was falling back to the package default — off.

`vendor:publish --tag=postmaster-migrations` then `migrate` brought
`add_confirmation_ip_to_postmaster_memberships_table` in. **A `composer update`
does not do that**, which this app already learned the expensive way at `0.1.3`
(docs/15) — five owed migrations that surfaced as fifteen failing Stripe tests
naming neither postmaster nor migrations.

## 2. The config had drifted four minors, and refreshing it nearly broke the app

The published `config/postmaster.php` was the `0.2` file. Diffed against the
package's, it was missing **whole sections**: `suppression`, `ingestion`,
`lists`, `subscription`, plus `admin.lists_path`, `admin.suppressions_path` and
`streams.marketing`.

**Nothing was broken by that**, and it is worth knowing why: every read in the
package passes a default — `config('postmaster.suppression.enabled', true)` and
so on — so a missing section behaves as the package intends rather than as
`null`. What it costs is legibility. An operator reading this file saw no
suppression, ingestion or lists configuration and would reasonably conclude the
package could not do those things, with no way to tune them short of opening
`vendor/`.

So the file was force-republished and the host's own values re-applied:
`prune_after_days => 400` (>13 months, a cross-year campaign audit trail),
`admin.layout => 'core::admin.layout'`, and the two `streams` values that come
from env.

### The trap, which was hit and caught

**`--force` reset the master switch.** `postmaster.enabled` went from `true`
back to the package's shipped `false`, and with it went the capture listeners
**and `PreventSuppressedRecipients`** — the send-time guard. The suite named it
immediately: *"it refuses a campaign to somebody who unsubscribed"* failed with
the campaign in the outbox.

**The verification that should have caught it before the suite did was wrong**,
and that is the part to keep. The first diff of old-versus-new parsed the file
into a flat `key => value` map — so `enabled`, which appears seven times at
different depths, collapsed to whichever occurrence came last. The check
reported four differences and declared the rest preserved while the single most
important value had been silently overwritten.

A structure-aware re-diff, tracking nesting so `enabled` and
`suppression.enabled` cannot collide, found the master switch and one other
thing: `driver` used to sit at the top level here and lives under `ingestion`
now, with the same value. The old top-level key had been dead for four minors.

**The rule: never verify a nested config file with a flat parser.** Duplicate
key names at different depths are the norm in a Laravel config, and a flat map
hides exactly the keys most likely to matter, because the general ones —
`enabled`, `path`, `driver` — are the ones that repeat.

## 3. Three new admin URIs arrived, and this app now guards against them

Postmaster contributes screens through `core.admin.plugins`, and the upgrade
took that from two screens to **five**: the message log gained
`suppressions`, `mailing-lists` and `mailing-lists/{list}`.

That is the surface of a defect this workspace has already paid for.
`AdminScreenRegistry` dedupes by **name**, while Laravel's `RouteCollection`
keys by method+URI — so two screens on one URI both register, the second
clobbers the first's route, and the admin navigation then 500s **every** admin
page on a `RouteNotFoundException` naming a route nobody deleted.
`projects/uclemmer` hit exactly this taking postmaster `v0.1.3`.

There is no collision today. `tests/Feature/Foundation/AdminScreenCollisionTest.php`
is what will say so tomorrow, and it was added now rather than after the fact
because the reason this app did not have it — that it contributes nothing of its
own to `/admin`, since its screens live at `/staff` — stopped being a good
reason the moment one provider's URI surface tripled without anybody here
choosing the URIs.

If it goes red, **do not rename by reflex**: the screen backed by the table that
is actually written to should keep the URI.

## Definition of done

- [x] `^0.2` → `^0.6`; the breaking change checked, not assumed
- [x] New migration published and run
- [x] Config refreshed to `0.6`, with every host value re-applied and verified
      by a nesting-aware diff
- [x] The master switch, reset by `--force`, restored — and how it was nearly
      missed written down
- [x] Admin screen collision guard added ahead of a collision
- [x] 984 of 985 tests green, the one skip pre-existing
