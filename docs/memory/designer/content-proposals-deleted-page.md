---
name: content-proposals-deleted-page
description: An editor's waiting change whose page is deleted or in the Recycle Bin: stale and told on Apply, or refused and still waiting; the count skips a gone page; "Deleted page" in the list (#10219)
module: content_proposals
---

## What does Apply do when the page is deleted?
- The page row is gone (deleted, or the Recycle Bin emptied): `pg_content_proposals_apply()` marks the change `stale` with error `page gone`, writes the editor's "Change can no longer be applied" bell line and takes back the Workspace request, as for a missing node. The row is kept, not dropped; it can only be dismissed.
- Answer: "The page of this change was deleted, so it cannot be applied."

## And when the page is in the Recycle Bin?
- It can be restored, so the change keeps waiting and is still counted. Apply refuses: "The page is in the Recycle Bin. Restore it to apply this change." Nobody is told.
- Which case: `_pg_cp_page_missing()` (page row, then `pg_recycle_page_binned()`) through the pure `pg_content_proposals_missing_kind()`; any other unreadable page keeps "The page could not be read."

## What do the count and the list show?
- `pg_content_proposals_waiting_count()` joins `page`: a waiting change whose page row is gone is not counted, at once, before anyone presses Apply.
- Waiting Content Changes (`content_changes.php`) names a page whose row is gone "Deleted page"; its stale note reads "The page of this change was deleted; it can only be dismissed."

## Why only on Apply?
- Nothing in the page delete or bin-empty path touches `design_proposals`, and no sweep was added (outside the bug's scope): the editor is told when a designer presses Apply, or answers yes on the Workspace request.

## Which test guards it?
- `tests/content_proposals_deleted_page_test.php` (apply on a fake database: gone, binned; the count; the screen's words).

Related: docs/memory/designer/content-proposals-designer-side.md, docs/memory/designer/content-proposals-bell.md
