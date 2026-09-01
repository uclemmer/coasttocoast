# 17 — "School" becomes "organization"

**Owner request, 2026-09-01.** Rename schools to organizations, completely — the words wherever they
appear, and the database tables and models with them.

## 1. The schema and the models were already right

The first finding is that there was no `schools` table, no `school_id` column and no `School` model
to rename. Card 1.2 created `organizations` on 2026-08-18 and every table that points at it has said
`organization_id` since (`users`, `registrations`, `grants`, `message_recipients`, which also carries
`organization_name`). `App\Models\Organization`, `OrganizationFactory`, `OrganizationPolicy`,
`OrganizationService`, the `/staff/organizations` screens and `/portal/organization` were all named
before this change.

So **no migration was written and no model was renamed**, and that is the answer to the obvious
question a reader of this doc will have. What existed instead was a vocabulary that had drifted away
from the schema: 1,068 occurrences of "school" across 150 files, in user-facing copy, docblocks,
local variables, method names, translation placeholders and test fixtures. The screen said "Schools",
the column said `organization_id`, and the two had been disagreeing since D8.

A test now pins both halves — see §4.

## 2. What "school" meant, and where it stays

The rename was not a global find-and-replace, because the word has two unrelated referents here and
only one of them is the entity.

**Renamed — the entity.** The college or university that registers, holds grants and appears on the
roster (D8). Everything from `Audience`'s eight descriptions ("Schools with a confirmed registration"
→ "Organizations with…") through `RegistrationNotAllowed::notYourOrganization()`'s message, the
signup picker's labels, the staff nav, the portal headings, the check-payment PDF's comments, and
every `$school` local and `$this->school` test property.

**Kept — everything else.** Two senses that a blind replace would have corrupted:

- **The four sponsoring preparatory schools** — Baylor School, Girls Preparatory School, McCallie
  School, St. Andrews-Sewanee School. They are `Sponsor` rows, not `Organization` rows, and the fair
  is *run by* them. This reaches further than the four names: `SponsorSeeder`'s docblock, the
  sponsors-page meta description, the "four school logos" TODO on the home page, the outstanding-asset
  rows in docs 05, 11 and 16, and `Sponsors\Index`'s note about "what integer puts a school second".
- **The high-school visitors** — "high school sophomores and juniors and their parents" in the
  content blocks and the FAQ, and "high schoolers" in the fonts rationale (D-8.1-a). These are the
  people the fair is *for*.

Two more that are neither: `171 Baylor School Road` is the coordinator's street address, in
`config/fair.php` and `.env.example`; and `Organization::factory()->named('Baylor School')` in
`OrganizationsTest` is arbitrary fixture text chosen to exercise name normalization.

`docs/design-handoff/` was left untouched on purpose. It is a received artifact with its own
`PROVENANCE.md`, and rewriting words inside it would falsify the record of what was delivered. Its
own occurrences are all sponsor- or high-school-sense anyway.

## 3. Two things the mechanical pass got wrong

Worth recording because both are properties of renames, not of this one.

**Articles.** "a school" becomes "a organization". 148 of them, spread through user-facing copy —
`__('This person is not currently a representative of a school.')` among them. The fix belongs in the
same pass as the replacement, not in a review afterwards, because nothing downstream flags it: it
compiles, it renders, and the tests assert on substrings that do not span the article.

**Published views do not update themselves** — the trap the workspace file already records for
paginators, arriving here in a different costume. `resources/views/vendor/core/auth/login.blade.php`
is this app's own copy of a laravel-core view, and it carries app-specific prose about signing up
claiming or creating an organization. The rename skipped it because the sweep excluded every directory
named `vendor`, which is correct for `/vendor` and wrong for `resources/views/vendor`. **The guard
test in §4 found it; no other check would have.**

## 4. The test

`tests/Feature/Foundation/VocabularyTest.php`, two assertions.

The first walks `app/`, `config/`, `database/`, `resources/views/`, `routes/` and `tests/`, strips the
non-entity phrases from §2, and fails on any line still containing "school". The allow-list is
deliberately **phrase-shaped rather than file-shaped**: a new sponsor line passes, and a new `$school`
variable in the same file does not.

The second reads the live schema and fails on any table or column whose name contains "school" — the
case the source scan structurally cannot see, since a migration adding `school_id` is a string in a
closure that a future `Schema::` call turns into a column name.

Why a test rather than this paragraph: writing `$school` for an `Organization` fails nothing. It
compiles, it renders, the suite stays green, and the drift comes back one file at a time — which is
exactly how 1,068 of them accumulated after the schema had already been named.

## 5. Verification

- `php artisan test` — 796 passing (794 before, plus this doc's two).
- `vendor/bin/pint --dirty` — clean.
- No behavioural change: no table, column, route, permission, enum value or config key moved. Every
  edit is copy, comment, local identifier or test fixture. The suite passed unmodified before the
  guard test was added, which is the evidence for that claim.

The dev SQLite database was not touched. Its seeded content is regenerated by
`php artisan migrate:fresh --seed`, and the seeders carry the new wording.
