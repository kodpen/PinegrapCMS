---
name: designer-collab-notes-sync
description: How a note left in the designer reaches the other editor without a reload: presence notes map, page_notes_at counter, notes_fetch, merge rules.
---

## How does the other side learn about a new note?
- **The signal rides the heartbeat.** The `presence` response carries a `notes` map (`page_id => page_notes_at`); a changed number triggers `designer/notes_fetch`. Do not open a new polling loop.
- **`page.page_notes_at` is an integer, not a signature.** The alternative was reading every page's `page_tree_json` each heartbeat; the column is LONGTEXT, one per tab, and the answer is almost always "unchanged". `pg_collab_notes_ready()` is defensive.

## What does notes_fetch return and how is it merged?
- **`notes_fetch` returns no tree.** The asker is mid-edit; sending a tree forces "discard your work" vs "merge two layouts". The notification makes neither choice.
- **Merge never overwrites a note being written locally and never deletes any note.** Criterion: `_notes` present + `_notes_at` absent => a note this browser has not sent yet, it wins. A note deleted remotely stays on screen until refresh.
- **Merge does not call `saveState()` and does not touch the dirty flag**: a change the operator did not make cannot be the reason for a save prompt.
- `_collabPersistNote()` runs for **every role**; for someone with save authority a failure is silent (most likely the node is not yet in the saved tree, which is not an error for them).

Related: docs/memory/designer/designer-collab-locking.md
