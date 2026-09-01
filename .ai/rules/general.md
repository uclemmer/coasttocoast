---
paths:
  - '**'
---

# General

## Stay in this repo; sibling projects are not your concern
Owner, 2026-09-01. Work in coasttocoast is scoped to coasttocoast. Do not audit, report on, or tidy sibling projects in the workspace — dirty working trees, untracked directories or moved submodule pointers elsewhere under projects/ are someone else's in-flight work, and surfacing them is noise.

The parent repo still needs its pointer commit for changes made here (a change is two commits) — that is part of this project's work, not a sweep of others.

The one exception: if a change here depends on a uclemmer/* package, checking that package in projects/packages/ is in scope. Read it, and say so if a fix belongs there rather than here.
