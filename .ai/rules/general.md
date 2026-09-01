---
paths:
  - '**'
---

# General

## Stay in this repo; sibling projects are not your concern
Owner, 2026-09-01. Work in coasttocoast is scoped to coasttocoast. Do not audit, report on, or tidy sibling projects in the workspace — dirty working trees, untracked directories or moved submodule pointers elsewhere under projects/ are someone else's in-flight work, and surfacing them is noise.

The parent repo still needs its pointer commit for changes made here (a change is two commits) — that is part of this project's work, not a sweep of others.

The one exception: if a change here depends on a uclemmer/* package, checking that package in projects/packages/ is in scope. Read it, and say so if a fix belongs there rather than here.

## The entity is an Organization, never a School
Renamed 2026-09-01 (docs/17). The college or university that registers, holds grants and appears on the roster is an `Organization` — in copy, docblocks, variables and test fixtures, not just in the schema. Do not write `$school`, `schools()` or "Schools" for it.

Two other referents keep the word and must not be renamed: the four sponsoring preparatory schools (Baylor, GPS, McCallie, St. Andrews-Sewanee — they are `Sponsor` rows), and the fair's high-school visitors. `171 Baylor School Road` is a street address.

`tests/Feature/Foundation/VocabularyTest.php` enforces both halves (source scan with a phrase-shaped allow-list, plus a live-schema check). Add to that allow-list only for a genuine non-entity sense.
