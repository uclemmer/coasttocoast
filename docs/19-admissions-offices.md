# 19 — The admissions office behind each organization

**2026-09-01.** Doc 18 imported the roster and said, in as many words, what it
could not fill in: the participant export described a *person* — a
representative's name, work address and mobile — and knew nothing about the
institution. Every one of the 156 organizations landed with a null `website`,
`admissions_office` and address, and with a representative's own email sitting
in `admissions_email` because that was the only address available and an
organization with none is dropped from every campaign.

This closes that gap. **Every one of the 156 organizations now carries its admissions
office**: which office it is, its page, its own address, its own phone number
and its own inbox.

## The office, not the university

The distinction is the point, and it decided every field:

| Column | What went in | What deliberately did not |
|---|---|---|
| `website` | The admissions office's page — `admissions.vanderbilt.edu/contact/` | The institution's front door, `vanderbilt.edu` |
| `admissions_office` | The office's own name, as it styles itself: "Office of Undergraduate Admission", "SCAD Admission Department", "Admissions and Records" | A generic label applied to all 156 |
| `admissions_email` | The office's published inbox — `apply@admission.clemson.edu` | The campus switchboard's `info@`, or a named person's address |
| `admissions_phone` | The admissions line | The university's main number |
| address | The admissions mailroom — a PO box or a named hall, which is frequently *not* the campus street address | The institution's postal address |

A coordinator posting an invitation or chasing a registration needs the office.
That is what is in there.

## Coverage

`database/seeders/data/admissions-offices.json`, 156 records:

| Field | Filled |
|---|---|
| `admissions_office` | 156 / 156 |
| `website` | 156 / 156 |
| `admissions_phone` | 155 / 156 |
| `city` | 155 / 156 |
| `address_line1` | 154 / 156 |
| `state` | 154 / 156 |
| `postal_code` | 149 / 156 |
| `admissions_email` | **143 / 156** |
| `logo_source` | 156 / 156 |

**Every organization on the roster now has a record**, and it did not used to.
`JROTC` was the one without: not an institution, no admissions office to look
up, so nothing to research. It was flagged here as a deletion-or-merge decision
for the coordinator, and on 2026-09-02 the owner took it — it is excluded at the
source now (doc 18), which is why the roster is 156 rather than 157 and this
table's denominator matches it exactly.

### The thirteen without an office inbox

Bard, Dalton State, Georgia State, George Washington, Miller-Motte, Muhlenberg,
North Carolina Outward Bound School, Piedmont, Presbyterian, UT Knoxville, the
Naval Academy, Florida, and the Tennessee Army National Guard.

Two reasons, both of which are the institution's choice rather than a gap in the
research. Several route admissions enquiries through a web form and publish no
address at all (the Naval Academy and GWU say so explicitly). Several obscure
the address against scrapers, so it is visible in a browser and not in the page
source. A few publish only a named director's address, which was **not**
recorded — a person is not an office and they move on.

Those thirteen keep the representative's address from the export, which is worse
than an office inbox and much better than null: `AudienceBuilder` drops an
organization with no `admissions_email` from every campaign (doc 07 §2 rule 1).
They show as gaps in `/staff/organizations`, which is where a human closes them.

## Two rules that cost coverage on purpose

**Nothing is derived.** A website is never guessed from an email domain, and an
inbox is never guessed from a pattern. Both would have filled every row. Both
produce a wrong value that reads like a researched one — `mailbox.sc.edu` and
`em.ufl.edu` are real domains in the export and neither is a website. A field
nobody published is null, and a null is honest.

**No individuals.** See above. There is a test for this: no `admissions_email`
in the data file has a `first.last` local part unless one half is a role word,
so `admissions.office@trincoll.edu` passes and `j.smith@` could not have got in.

## How it is applied

`AdmissionsOfficeSeeder`, run after `OrganizationSeeder` and
`RegistrationSeeder`:

```bash
php artisan db:seed --class=Database\\Seeders\\AdmissionsOfficeSeeder
```

It **fills** `admissions_office` and `website` only when they are empty, and it
never overwrites.

The **address moves as one thing**, not as five independent columns, and that
was a bug before it was a rule. Filled field by field, an organization already
carrying a street and a city gains this office's "Fulford Hall" on
`address_line2` and ends up with a third address that belongs to nobody. Caught
in development — Sewanee is a fixture organization with an invented address —
but it would happen to any organization whose rep filled in half a profile. So
the address is written only onto an organization that has none of it.

It **replaces** `admissions_email` and `admissions_phone` only where the value
sitting there is one a representative gave rather than one a person chose. That
is asked two ways, and the second exists because the first was not enough:

1. The value matches one of that organization's own `registrations.rep_email` /
   `rep_phone` values. Enough on a real host, and it needs nothing but the
   database.
2. The value appears in the participant export for that organization at all.
   Consulted only when the export is on the machine, which is why
   `AdmissionsOfficeSeeder` extends `ParticipantExportSeeder`.

Anything matching neither is a deliberate entry and is left alone.

Idempotent, and it must run **after** `OrganizationSeeder` — it only updates
organizations that already exist, and the value it replaces is the one that
seeder wrote.

### Why check (2) had to exist

Check (1) is right about a real host and wrong in development, and the reason
generalises past this seeder. `OrganizationSeeder` takes an organization's
address from its **latest** submission; `RegistrationSeeder` **skips a fair the
organization is already registered for**. When a fixture holds that year, the
address is real and the registration that would prove where it came from was
never written — so the fingerprint looks for something that does not exist.

Seven organizations sat on a named representative's personal address with the
published inbox available and unused: Appalachian State, Belmont, Berry, Emory,
Rhodes, Vanderbilt, Wofford. Each individually explicable, none visible without
comparing the seeded rows against the source file — which is now
`SeederTest`'s job.

## The fixtures do not invent institutions any more

**Owner, 2026-09-01**, overruling this doc's earlier position that "in
development the fixtures win, and that is correct". They do not, and it was not.

`FairFixtureSeeder` names its organizations after real colleges so a local
roster is worth looking at, and `OrganizationFactory` invented a website, an
inbox, a phone and an address to go with each name. On an invented name that is
placeholder data. On a real one it is **wrong** data — and because both
real-data seeders only fill columns that are EMPTY, it did not merely sit there
looking odd, it blocked the researched value from ever landing. Twenty-six
organizations carried faker output, `https://sawayn.com` on Rhodes College among
them, while the real admissions page sat in the seed data unused.

The fix is at the source: `OrganizationFactory::withoutInstitutionalProfile()`,
applied by `FairFixtureSeeder::organization()` to every organization it creates.
A fixture organization now has a real name and nothing else claimed about the
institution behind it, and the real-data seeders fill in the rest. Whatever
neither covers — Bryan College, Hendrix, Millsaps and the handful of fixture
organizations that never attended a real fair — stays visibly empty in
`/staff/organizations`, which is a gap a coordinator can close rather than a
plausible-looking lie nobody thinks to check.

One organization sets its own contact detail: `Maryville College` needs a
generic address for the campaign-fallback fixture to mean anything, so it is set
here rather than left to this seeder. It is Maryville's real published inbox
rather than an invented one, so it agrees with the researched data instead of
blocking it — and `SeederTest` fails if the two ever drift apart.

**Nothing about production changed.** Verified rather than assumed: seeded
without fixtures, all 156 organizations and 156 contact upgrades land and
nothing disagrees with the source file. The bug only ever existed where fixtures
and real institutions shared a database.

## Logos

`organizations.logo_path` is a **file on the public disk** — the roster and the
admin panel both render it through `Storage::disk('public')->url()` — so a
remote URL cannot go in it. Something has to fetch the bytes:

```bash
php artisan fair:fetch-organization-logos --dry-run   # resolve and report, download nothing
php artisan fair:fetch-organization-logos             # fetch and store
```

**The logo URL is discovered, not recorded**, and that was a deliberate choice
over hand-collecting 156 image URLs. A hand-collected list is 156 guesses at a
path that changes whenever a university touches its stylesheet, and the failure
is silent: the URL 404s and the roster tile quietly falls back to a letter. The
command reads each institution's own metadata instead, from the `logo_source` in
the data file:

1. `mask-icon` — a vector, so it never needs upscaling
2. `apple-touch-icon` — square, the mark alone, **largest declared first**
3. `<link rel="icon">` — the favicon as declared, largest first
4. `og:image` — what the institution nominates for sharing
5. `/favicon.ico`

**`apple-touch-icon` is ahead of `og:image` on purpose**, which is the opposite
of what a link preview does. Running it the other way round is how that was
found: Clemson's `og:image` is an aerial photograph of the campus. Most
institutions nominate a hero photo for sharing, because that is what sharing is
for.

### Taking the first declared icon was wrong, and it cost 22 logos

**Fixed 2026-09-01.** The list above used to be four regexes, and the command
matched one, took match `[0]`, and committed to it. A site that supports iOS
properly declares an `apple-touch-icon` per device generation and writes the
**smallest first**, because the list is historical — Auburn ships 57, 72, 76,
114, 120 and 144. So Auburn's roster tile was a 57px image upscaled into a space
four times its size, with a 144 sitting in the same `<head>`.

It now collects every candidate, reads the `sizes` attribute, orders by it, and
stops as soon as one reaches 180 — the modern single-icon size, so most sites
still cost one request. An `apple-touch-icon` with no `sizes` is read as 180,
which is why it is usually the unsized one. `rel="shortcut icon"` is two tokens
meaning `icon`, and is parsed as such rather than missed.

Each candidate is then **measured** rather than trusted:

- **Wider than 2:1 is rejected** as a banner or a photograph. Mississippi
  State's `og:image` is 2400x800. This is why `og:image` can stay in the list
  without being dangerous — it is ranked late and rejected on shape, rather than
  being trusted because it matched.
- **An ICO is measured by its largest frame.** An ICO is a container:
  `favicon.ico` routinely holds 16 through 256 in one file and the browser picks.
  `getimagesize()` reports the FIRST directory entry, conventionally the
  smallest, so measuring one the ordinary way calls a 256x256 file 16x16. Bard,
  Trinity and the Air Force Academy all read as unusable that way and are all
  fine. **The first review of this data made exactly that mistake**, so part of
  what looked like a fix was the measurement being corrected rather than a logo
  being replaced.
- **Unmeasurable is not unusable.** An SVG has no pixel size and is the best
  logo there is. Anything the measurement cannot judge scores neutrally and is
  still stored; this ranks candidates and rejects obvious banners, and it
  rejects nothing it cannot read.

**Below 96px is kept, not rejected** — a small mark still beats a letter — but
counted, in a `stored but small` tally. That is the hand-upload worklist, and it
is 29 organizations: Rhodes, Ohio State, Virginia Tech, Puget Sound and the rest
declare nothing but a small favicon, and no amount of resolution order finds
what a site does not publish.

Measured over the real roster: 96 usable of 143 before, **118 of 148 after**,
with every wrong-shaped image gone.

**Storing a logo deletes the organization's other logo files**, and that is not
housekeeping. The stored name is `<slug>.<extension>`, so a run that picks a
different format leaves the old one behind — and the disk fallback above prefers
richer formats, so a superseded `rice-university.webp` would outrank the `.ico`
that replaced it and come back on the next refusal. The fallback would
resurrect exactly the 1500x600 banner the shape check had just thrown out.
Found by combining the two features, each of which was right on its own.

Clemson has no touch icon either, so it still resolves to the aerial photo —
which is the honest summary of this command. **It is an accelerator, not an
oracle.** Run `--dry-run` first, read the list, and let the coordinator upload a
real logo where the guess is bad. That upload field already exists and always
was the answer.

### A refusal is not "no logo"

**Added 2026-09-01, after it cost two logos.** When a fetch fails for any reason
— the site refuses the request, the nominated URL is not an image, the file is
too big — the command now looks for a copy this organization already has in
storage and keeps that instead of leaving the column null. Filenames are the
organization's slug, so it needs no record of what was fetched before, and it
reports every recovery rather than doing it quietly. `--dry-run` says what it
would keep and writes nothing.

Roughly one site in twenty answers a scripted request with a 403 or a 406 one
day and serves the file the next: Rice and North Carolina Outward Bound both
did, on consecutive runs.

The reason that is a real bug rather than an inconvenience is the split between
where the two halves live. **`logo_path` is a database column and the files are
not**, so any reseed nulls all 141 while every file survives on disk. The
refetch afterwards is where the loss happens, to a different arbitrary handful
each time depending on who is refusing that afternoon — and the loss is
invisible, because a null column looks exactly like an institution that
publishes nothing.

It still reports `unreachable` when there is nothing to fall back to. The
fallback recovers a file; it does not paper over a gap.

It will not overwrite a logo that is already set (`--force` overrides), refuses
anything that is not an image or is over 2 MB, and follows only the published
metadata — there is no crawling. These are third-party marks used to identify
organizations that chose to attend the fair, which is the same use the existing
upload field is for; deleting a row's logo in `/staff/organizations` is the
whole remedy if an institution objects.

## Why this data file *is* committed

Doc 18 records the owner's directive that the participant export stays out of
the repository, because it is ~380 real people's names, work addresses and phone
numbers.

This file is the opposite kind of thing: an office, a published inbox, a
published number, a public street address — institutional information that these
organizations put on their own websites for exactly this purpose, with no
individual's details in it. It is committed so the seeder is reproducible, the
values are reviewable in a diff, and a correction is a pull request rather than a
database edit somebody has to remember.

## What it is not

**It was gathered by machine on 2026-09-01 and not verified one institution at a
time.** It is a good starting point, not checked fact. Phone numbers and inboxes
move; a handful of the mailing addresses are the office's mailroom and a handful
are the campus's, depending on what the institution published. Every field is
editable in `/staff/organizations`.

Two known specifics worth carrying forward:

- **Sub-units share their parent's office.** Tennessee Tech's four colleges and
  Mississippi State's College of Agriculture point at the central admissions
  office, because that is who handles their admissions. They stay separate
  organizations — doc 18 explains why merging them would delete a real
  registration.
- **UNG has campus-specific inboxes** (`admissions-dah@`, `-gvl@`, `-blu@`,
  `-cmg@`, `-ocn@`). Dahlonega is recorded as the primary campus; if the rep who
  attends is from another, that is a one-field edit.

`Washington & Lee University` and `Washington and Lee University` were two rows
with identical office details — the same institution under two spellings that do
not normalize together. **Resolved 2026-09-01** (owner): the ampersand spelling
is now a `CANONICAL_NAMES` entry in `ParticipantExportSeeder`, the two rows here
are collapsed into one, and the organization seeds once as `Washington and Lee
University` carrying both its fairs. Nothing is left for the Merge action.

It is worth knowing how it was found, because none of the guards could see it.
`normalizeName()` strips `&` to a space, so `X & Y` and `X and Y` normalize to
different strings and **can never collide** — the duplicate warning is blind to
the pair, and so is this file's own "no organization twice under a different
spelling" test, which only looks for two keys normalizing alike. What surfaced
it was two rows sharing a byte-identical logo. The other pair of this shape,
Missouri University of Science and Technology, was caught earlier the same way
and is already in that map.

## Tests

- `tests/Feature/Foundation/AdmissionsOfficeSeederTest.php` — the fill, the
  narrow replacement rule, both "do not overwrite" cases, idempotence, and four
  assertions about the data file itself (coverage, no duplicate normalized
  names, every website is the office rather than the front door, no individual's
  address).
- `tests/Feature/Console/FetchOrganizationLogosTest.php` — the resolution order
  including touch-icon-beats-og:image, every fallback, dry run downloading
  nothing, the two refusals, an unreachable site, and the five cases of keeping
  a copy already on disk (a refusal, a non-image, richest format wins, still
  reporting `unreachable` with nothing to keep, and writing nothing on a dry
  run).

Both build their organizations with factories rather than seeding the participant
export, so they run on a machine that does not have it — which is every machine
but the owner's.
