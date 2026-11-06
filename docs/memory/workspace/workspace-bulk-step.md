---
name: workspace-bulk-step
description: The slice runner of Workspace bulk changes (ws_bulk_step()): the status read again under the lock (ws_bulk_step_verdict()), a start still matching waited for, an update's place kept before its write
---

## Who runs a bulk change's slices?
- `ws_bulk_start()` (from Apply, after the row is taken as `applying`), the screen's step action, the screens' tick and the Workspace job (`ws_bulk_continue()`). One at a time per change: `GET_LOCK('pg_ws_bulk_<id>', 0)`.

## What is checked after the lock is taken?
- `ws_bulk_step_verdict()` on the row read again: `stop` when it is no longer `applying` (another worker finished or failed it), `wait` while its snapshot has no `ids` and it was taken less than `WS_CHANGE_APPLYING_TIMEOUT` ago, `run` with its records, `invalid` (failed) for a start that died long ago.
- Before: the row was not read for its status under the lock, so a worker that got the lock just after another finished posted a second decision; and a tick that reached a change while `ws_bulk_start()` was still matching failed it with "Invalid request." (`tests/workspace_bulk_step_test.php`).

## Why is the state saved before each update or delete?
- `pos` is counted before the record; saving it before `ws_change_write()` means a request that dies mid-slice resumes after that record. Before, the state was saved at the slice's end only, so a `percent` or `add` change raised up to 40 records twice on resume. Create rows did this already (`workspace-bulk-create.md`).

Related: workspace-bulk-create.md, workspace-record-changes.md
