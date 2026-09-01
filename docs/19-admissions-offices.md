# 19 — The admissions office behind each organization

**2026-09-01.** Doc 18 imported the roster and said, in as many words, what it
could not fill in: the participant export described a *person* — a
representative's name, work address and mobile — and knew nothing about the
institution. Every one of the 158 organizations landed with a null `website`,
`admissions_office` and address, and with a representative's own email sitting
in `admissions_email` because that was the only address available and an
organization with none is dropped from every campaign.

This closes that gap. **157 of the 158 organizations now carry their admissions
office**: which office it is, its page, its own address, its own phone number
and its own inbox.

## The office, not the university

The distinction is the point, and it decided every field:

| Column | What went in | What deliberately did not |
|---|---|---|
| `website` | The admissions office's page — `admissions.vanderbilt.edu/contact/` | The institution's front door, `vanderbilt.edu` |
| `admissions_office` | The office's own name, as it styles itself: "Office of Undergraduate Admission", "SCAD Admission Department", "Admissions and Records" | A generic label applied to all 157 |
| `admissions_email` | The office's published inbox — `apply@admission.clemson.edu` | The campus switchboard's `info@`, or a named person's address |
| `admissions_phone` | The admissions line | The university's main number |
| address | The admissions mailroom — a PO box or a named hall, which is frequently *not* the campus street address | The institution's postal address |

A coordinator posting an invitation or chasing a registration needs the office.
That is what is in there.

## Coverage

`database/seeders/data/admissions-offices.json`, 157 records:

| Field | Filled |
|---|---|
| `admissions_office` | 157 / 157 |
| `website` | 157 / 157 |
| `admissions_phone` | 156 / 157 |
| `city` | 156 / 157 |
| `address_line1` | 155 / 157 |
| `state` | 155 / 157 |
| `postal_code` | 150 / 157 |
| `admissions_email` | **144 / 157** |
| `logo_source` | 157 / 157 |

**The 158th is `JROTC`**, which submitted from an `aol.com` address. It is not
an institution and has no admissions office to look up, so it has no record and
keeps the representative's address. It is a candidate for deletion or for
merging into whichever school's unit it was, and that is the coordinator's call.

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

It **replaces** `admissions_email` and `admissions_phone` under one narrow rule:
only when the value sitting there is one of that organization's own
`registrations.rep_email` / `rep_phone` values. That is exactly the fingerprint
of `OrganizationSeeder` having copied a representative's address up into the
office column — it needs no provenance flag, it survives the export being
absent, and it cannot match something a coordinator typed. Anything else is a
deliberate entry and is left alone.

Idempotent, and safe to run in any order relative to the other two.

**In development the fixtures win, and that is correct.** `FairFixtureSeeder`
invents eighteen organizations whose names are real institutions — Vanderbilt,
Rhodes, Belmont — with faker websites and addresses. The seeder will not
overwrite them, so a local database shows Vanderbilt with a `hoeger.com`
website. Production has no fixtures, so it does not arise there; the alternative
would be a seeder that talks over existing data, which is worse.

## Logos

`organizations.logo_path` is a **file on the public disk** — the roster and the
admin panel both render it through `Storage::disk('public')->url()` — so a
remote URL cannot go in it. Something has to fetch the bytes:

```bash
php artisan fair:fetch-organization-logos --dry-run   # resolve and report, download nothing
php artisan fair:fetch-organization-logos             # fetch and store
```

**The logo URL is discovered, not recorded**, and that was a deliberate choice
over hand-collecting 157 image URLs. A hand-collected list is 157 guesses at a
path that changes whenever a university touches its stylesheet, and the failure
is silent: the URL 404s and the roster tile quietly falls back to a letter. The
command reads each institution's own metadata instead, from the `logo_source` in
the data file:

1. `apple-touch-icon` — square, usually 180px, usually the mark alone
2. `og:image` — the image the institution nominates for sharing
3. `<link rel="icon">` — the favicon as declared
4. `/favicon.ico`

**`apple-touch-icon` is ahead of `og:image` on purpose**, which is the opposite
of what a link preview does. Running it the other way round is how that was
found: Clemson's `og:image` is an aerial photograph of the campus. Most
institutions nominate a hero photo for sharing, because that is what sharing is
for.

Clemson has no touch icon either, so it still resolves to the aerial photo —
which is the honest summary of this command. **It is an accelerator, not an
oracle.** Run `--dry-run` first, read the list, and let the coordinator upload a
real logo where the guess is bad. That upload field already exists and always
was the answer.

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

`Washington & Lee University` and `Washington and Lee University` are still two
rows with identical office details — the same institution under two spellings
that do not normalize together. Doc 18 left it for `/staff/organizations` →
Merge, and this did not change that.

## Tests

- `tests/Feature/Foundation/AdmissionsOfficeSeederTest.php` — the fill, the
  narrow replacement rule, both "do not overwrite" cases, idempotence, and four
  assertions about the data file itself (coverage, no duplicate normalized
  names, every website is the office rather than the front door, no individual's
  address).
- `tests/Feature/Console/FetchOrganizationLogosTest.php` — the resolution order
  including touch-icon-beats-og:image, every fallback, dry run downloading
  nothing, the two refusals, and an unreachable site.

Both build their organizations with factories rather than seeding the participant
export, so they run on a machine that does not have it — which is every machine
but the owner's.
