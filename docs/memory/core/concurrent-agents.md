---
name: concurrent-agents
description: Several agents or threads on the repo at once: own branch and area, rebase before push, migration sub-step numbers that do not collide
---

## How do parallel agents stay out of each other's way?
- Each task on its own branch from `development`; touch only your own area's files; rebase onto `development` right before pushing.
- Shared files are append-only, each writer in its own section (list in docs/memory/workflow/git-branches-and-shared-files.md).
- Avoid two threads on the same plan file, note or code area at once.

## How are migration sub-step numbers kept unique?
- Take the number right before pushing, from the open version's file on `origin/development` (`includes/migrations/<open version>.php`), never from notes. If two PRs got the same number, renumber before merging.
- Labels are comment labels only and may shift when the chain is fixed; the call order in `upgrade_to_<version>()` is what runs.
- Ranges per area: docs/memory/release/release-status.md.
