# 18 — The participant export, and the two seeders that read it

**2026-09-01.** The owner supplied the export card 6.6 had been waiting for since the build started.
Standing answer A3 in [10-implementation-decisions.md](10-implementation-decisions.md) — *"Is there
a real ISPEUS export? **No.** Build an idempotent importer against a documented CSV schema; the
owner runs it when the file exists."* — is answered. The file exists.

It is not the CSV that answer described. It is `participants.json`, a dump of the old site's
registration-form submissions, and it carries none of the institutional columns
`fair:import-roster` was designed around. This doc records what is in it, what was built to read it,
and every judgement made in between.

## What arrived

`storage/app/private/participants.json`, read verbatim and left where it landed — see
[The export stays out of the repository](#the-export-stays-out-of-the-repository) below. 381
submissions, each carrying the fair it belongs to:

```
id, event_id, first, last, organization, email, phone, message, created_at, updated_at,
event: { id, name, slug, starts_at, ends_at, registration_starts_at, registration_ends_at, … }
```

The `event.slug` values are `college-fair-2023` through `college-fair-2026`, which match
`EventSeeder`'s slugs exactly — the back catalogue that doc 03 seeds "so there is somewhere to
import history into" turned out to line up with no adjustment at all. There is no 2022 roster in the
export; that fair stays empty rather than being filled with anything.

**What it does not contain**, and therefore what nothing downstream may claim to know:

| Absent | Consequence |
|---|---|
| Payment of any kind | The old form took no money. Nothing records who paid, how, or how much |
| Attendance | It is a sign-up log. A registration that did not turn up looks identical |
| Website, address, admissions office | The form asked for a person, not an institution |
| Any account, password or membership | Nobody in the export has ever signed in to this application |

## Why a seeder and not the importer

`fair:import-roster` still exists, is still the documented path for a CSV, and was not changed. It
was not used here because the shapes do not meet: the command reads fifteen named CSV columns and
the export has eight JSON keys, four of which the command has no place for. Massaging the JSON into
that CSV would have meant a throwaway conversion script whose decisions — which spelling of an
organization wins, which of two submissions is the real one — would then be invisible in the CSV it
produced.

Seeding reads the owner's file directly. The decisions live in code, next to their reasoning, under
test.

Three files:

| File | Job |
|---|---|
| `database/seeders/ParticipantExportSeeder.php` | Abstract. Reads the export, decides which submissions are one organization, orders them |
| `database/seeders/OrganizationSeeder.php` | 158 organizations |
| `database/seeders/RegistrationSeeder.php` | 354 places at four fairs |

```bash
php artisan db:seed --class=Database\\Seeders\\OrganizationSeeder
php artisan db:seed --class=Database\\Seeders\\RegistrationSeeder
```

Both are idempotent, and conservatively so — an organization that already exists has only its
**empty** columns filled, and a registration that already exists is not touched at all. Re-running
cannot talk over a cancellation, a refund, or a correction the coordinator made by hand.

## Where they run, and where they deliberately do not

`DatabaseSeeder` calls both, **last**. Two reasons, both load-bearing:

- `FairFixtureSeeder` does nothing at all if any organization exists. Putting the real history first
  would silently cost the development fixtures — the membership states, the grants in every status,
  the duplicate-name pair, the awkward registrations.
- `RegistrationSeeder` will not invent an organization it cannot find, so `OrganizationSeeder` has
  to have run.

`ProductionSeeder` does **not** call them, and that is not an oversight. Its contract is that it
invents nothing and is safe on every deploy; `SeederTest` asserts it creates zero organizations and
zero registrations. Loading a roster is a deliberate one-off — these two by name, or
`fair:import-roster` — not something that should happen again every time someone deploys.

The two seeders overlap the fixtures in dev: 18 of the 158 organizations already exist by name from
`FairFixtureSeeder`, and 19 of the 354 registrations are already held by fixture rows. Those are
matched and left alone, which is why a development seed reports 140 created rather than 158.

## The judgements

Each of these is a decision the export could not make for itself. All are reversible in the admin
panel.

### 1. 381 submissions are 354 registrations

The export is a form log, not a roster. Twenty-seven submissions are second attempts — a
double-click a second apart, a corrected email address, a colleague signing the same organization up
two months later. Connecticut College submitted four times in seven seconds for the 2024 fair.

Rows are grouped by (fair, organization) and the **latest submission wins**: it is the last thing
the organization told us about who is coming. The set-aside submissions are written into `notes`
when they came from a *different* person, so a coordinator can see that two people signed the same
organization up — that is an ordinary thing that happens and is worth knowing before the table is
set. A duplicate from the same address adds no note.

### 2. Which spellings are one organization

Grouping is by `Organization::normalizeName()` — the same function behind the duplicate warning
(R2.7) and the importer's matching — so "RHODES COLLEGE" and "Rhodes College" are one organization
here for the same reason they are one in the application. That collapses 182 submitted spellings to
170.

Where several spellings survive normalizing, the **most frequently submitted one wins** and a tie
goes to the most recent — the organization's own latest word on how it writes its name.

Fourteen entries in `CANONICAL_NAMES` handle what normalizing cannot see, taking 170 down to 158:
an abbreviation (`UAH`), truncated form fills (`Rh`, `Valdosta State Univer`), a typo that outlived
three fairs (`Middle Tennessee State Unviersity`), parentheticals and campus suffixes. **Every entry
is evidenced by the submissions sharing an email domain**, and every one was checked not to merge
two organizations that attended the same fair separately.

**It does not merge a university with its own colleges**, and that restraint is the reason the map
is hand-written rather than derived from email domains. Tennessee Tech submitted under four names on
`tntech.edu` and Mississippi State under two on `msstate.edu` — and at the 2024 fair, Tennessee Tech
University and its College of Education each registered. Folding by domain would have deleted a real
registration. A test pins that pair at two rows.

Near-duplicates the export cannot resolve stay as separate organizations. The admin merge action is
where a human decides otherwise, and the coordinator has better information than a seeder does.

### 3. `admissions_email` is filled from a representative's own address

This is the judgement most worth knowing about, because it writes a person's work address into a
column named for an office inbox.

`AudienceBuilder` drops an organization with no active rep and no `admissions_email` from every
campaign, and logs the drop (doc 07 §2 rule 1). None of these 158 organizations has an account
behind it. Leaving the column null would therefore seed 158 organizations that no win-back list can
ever reach — the exact failure importing history exists to prevent, arriving quietly as a log line
nobody reads.

So it is filled, from the most recent submission that organization made, along with
`admissions_phone`. Both are the best address we have, and both are one edit away from being
corrected in `/staff/organizations`.

### 4. Nothing else about an organization is guessed

`website`, `logo_path`, `admissions_office` and the five address columns are left null. Deriving a
website from an email domain was considered and dropped: `mailbox.sc.edu` and `em.ufl.edu` are both
in the export, and both would produce a wrong URL that reads like a researched one. A null column is
honest and shows up in the admin panel as work to do.

### 5. No user accounts

No `User` rows are created — the same choice `fair:import-roster` makes, and the reason
`registrations.user_id` is nullable at all. Creating active rep accounts for 380 real people would
hand them logins they never asked for and put them straight into campaign audiences; creating
retired ones would leave every organization unreachable anyway. They claim their organization
through signup (R2.7) when they come back.

### 6. Three registration fields are convention, not fact

Stated here because they read like data and are not:

- **`status` is Confirmed.** The export is the list of organizations that signed up for a fair that
  has since happened. Nothing in it distinguishes one that then failed to appear, so nothing
  pretends to.
- **`payment_method` is Check.** The old site had no gateway and the fair was paid by check.
  `Stripe` would be the false one; null means "made free by a grant", which none of these were. Same
  choice as the importer.
- **`price_cents` is the fair's list price** — and for 2023–2025 that price is itself a
  reconstruction (`EventSeeder`, doc 03). A registration's price is normally the snapshot of what
  was actually charged (N1); here it is the best figure available. Nothing downstream reads a past
  fair's price, so this is a record rather than an input, but it should not be mistaken for a
  receipt.

`confirmed_at` and the row's `created_at` are the real submission timestamp, so the timeline in the
admin panel is the true one.

Phone numbers are stored exactly as typed. The export has twelve different formats — `(423)
555-0100`, `4235550100`, `+1 (248) 555-0100` — and the application has no normalizer anywhere, so
imposing a house format here would make these rows unlike every row a rep has ever created.

## The export stays out of the repository

**Owner directive, 2026-09-01.** The export is real contact data for ~380 real people, and it lives
at `storage/app/private/participants.json`, which is gitignored. It is not committed, and it is not
to be. The first pass of this work did commit it, to a `database/seeders/data/` folder; that was
reversed the same day and the folder is gone.

The seeders read it from storage, so **they can only run where the file is**. That is the cost, and
it is paid deliberately: the names, work addresses and phone numbers of every admissions
representative who ever registered are not in anybody's clone, not in a fork, and not in whatever a
future CI runner has read access to.

**The risk that buys is not a crash — it is a roster that seeds empty**, with no error and no log
line, invisible until a win-back campaign resolves to nobody months later. Nothing is allowed to
degrade quietly, so the absence is loud in three places:

| Where | What happens with no export |
|---|---|
| Either seeder run **by name** | Throws, naming the path. You asked for the roster; it cannot be produced |
| `DatabaseSeeder` | Checks `ParticipantExportSeeder::available()` first, warns with the path, and seeds the fixtures without them. A developer who was never given the file still gets a working local app |
| The test suite | `ParticipantExportSeederTest` skips every assertion with the path in the message, rather than passing against an empty database. `ParticipantExportMissingTest` runs everywhere and proves the three behaviours in this table |

That second row is the one to remember when reading a seed log: **"140 organizations" and "no
participant export — seeded the fixtures only" are both successful runs**, and only the summary
lines tell them apart.

The seam is one method — `ParticipantExportSeeder::path()`. Everything else, including the tests,
asks it rather than naming a file.

### Getting the file onto a host

It is not deployed with the code. Copy it to `storage/app/private/participants.json` on the target,
run the two seeders, and then decide whether to delete it — nothing reads it after the seed, and the
data it carried is in the database by then.

## What is seeded

| Fair | Registrations |
|---|---|
| `college-fair-2022` | 0 — no rows in the export |
| `college-fair-2023` | 72 |
| `college-fair-2024` | 87 |
| `college-fair-2025` | 99 |
| `college-fair-2026` | 96 |
| **Total** | **354**, across 158 organizations |

`tests/Feature/Foundation/ParticipantExportSeederTest.php` — 30 tests — pins all of it: the counts
per fair, the collapsing of duplicate submissions, each entry in the canonical-name map, the pair
that must *not* be merged, the absence of user accounts, the three stated conventions, and that
re-running creates nothing and overwrites nothing. All 30 skip where the export is not, so a green
run on a machine without it has proved nothing about the roster — read the skip count.

`tests/Feature/Foundation/ParticipantExportMissingTest.php` is the half that runs everywhere.

## Still outstanding

- **The 2022 roster.** Not in this export. The fair is seeded and empty; if a 2022 list exists,
  `fair:import-roster` is still the way in.
- **Institutional detail.** Websites and addresses for all 158 organizations. Nothing needs them to
  work, but a roster entry with a website is worth more than one without.
- **Near-duplicates for the merge queue.** A handful of organizations are plausibly the same
  institution under names that neither normalizing nor the canonical map can join. They are
  deliberately left for `/staff/organizations` → Merge.
