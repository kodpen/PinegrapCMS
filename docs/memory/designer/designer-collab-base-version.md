---
name: designer-collab-base-version
description: Page Design Studio save refuses a page written by somebody else since the tab loaded it (lapsed lock, #11430): the tree base hash, notes left out, what the tab sends and gets back
module: page_base_version
---

## Why is the lock not enough?
A lock lapses after `PG_COLLAB_STALE` (65 s) without a heartbeat (laptop asleep, frozen tab). Another person may open the page, publish and leave; the first tab's next heartbeat claims the free lock and its Publish wrote its old tree over theirs with no message (#11430, found by the bug hunt #11429).

## How is the later save detected?
- Every editor page carries `baseHash` = `pg_page_base_hash()` of the stored tree it loaded (`includes/fn/page_base_version.php`; a converted classic page reads it from the row, since its tree is not stored yet).
- Publish sends `base_hash` with every page that carries a tree. The save path (`includes/designer_screen.php`, after the lock check) refuses the page when `pg_page_base_is_stale()` says the stored tree has another hash: skipped like a locked page, warning "The page "…" was changed by somebody else after you opened it, so your changes to it were not saved. Reload the editor to see the latest version."
- The reply's `pages` entries carry `base_hash` of each written page, read after the save's own merges; the tab adopts it. A refused page keeps its old base, so it stays refused until reload.
- No base sent (an editor loaded before this fix, a picked-in page, the API) or a page without an id: not compared.

## Why a hash and not page_timestamp or a counter?
- `page_timestamp` moves with the scheduled-publish job, the designs list and the API, which leave the layout alone (`page-schedule.md`).
- A write counter is a schema change; the hash needs none.

## What does the hash leave out?
- Node note props `_notes`, `_notes_by`, `_notes_at` at any depth: `designer/note_save` writes them into the stored tree while the lock holder edits (`designer-collab-notes-sync.md`).
- Encoding: the tree is decoded and encoded again, so `[]`/`{}` slots and escaping do not count.

Related: docs/memory/designer/designer-collab-locking.md
