---
paths:
  - 'app/**'
---

# App

## Order organization lists by sort_name, never name
Any query that lists organizations alphabetically orders on `organizations.sort_name`, not `name`. `sort_name` is derived in `Organization::saving()` alongside `normalized_name`: accents folded, lowercased, punctuation stripped, leading `the`/`a`/`an` dropped. It files an organization under its displayed name — University of Alabama under U, never inverted to "Alabama, University of" — and stops "The University of ..." landing under T, away from its sibling campuses.

Do not sort on `normalized_name` instead. It looks equivalent but exists for duplicate matching, so tuning that heuristic would silently reorder every public list. Reasoning: docs/10 D-10-a.
