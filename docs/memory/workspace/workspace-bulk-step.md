---
name: workspace-bulk-step
description: The slice runner of Workspace bulk changes (ws_bulk_step()): status read again under the lock, a start still matching waited for, place kept before a write; generate asks Pinegrap AI's own readiness
---

## Who runs a bulk change's slices?
- `ws_bulk_start()` (from Apply, after the row is taken as `applying`), the screen's step action, the screens' tick and the Workspace job (`ws_bulk_continue()`). One at a time per change: `GET_LOCK('pg_ws_bulk_<id>', 0)`.

## What is checked after the lock is taken?
- `ws_bulk_step_verdict()` on the row read again: `stop` when it is no longer `applying` (another worker finished or failed it), `wait` while its snapshot has no `ids` and it was taken less than `WS_CHANGE_APPLYING_TIMEOUT` ago, `run` with its records, `invalid` (failed) for a start that died long ago.
- Before: the row was not read for its status under the lock, so a worker that got the lock just after another finished posted a second decision; and a tick that reached a change while `ws_bulk_start()` was still matching failed it with "Invalid request." (`tests/workspace_bulk_step_test.php`).

## Why is the state saved before each update or delete?
- `pos` is counted before the record; saving it before `ws_change_write()` means a request that dies mid-slice resumes after that record. Before, the state was saved at the slice's end only, so a `percent` or `add` change raised up to 40 records twice on resume. Create rows did this already (`workspace-bulk-create.md`).

## Who must be ready for a generate change? (PR #PRNUM)
- Pinegrap AI: `ws_bulk_generate()` writes the texts when the change is applied, with no model assistant running. `ws_bulk_input()` refuses a proposal by `ws_bulk_generate_ready()`: while a model assistant runs, it lets the assistant go around `ws_ai_missing()` (Pinegrap AI's own switch, application, licence) and leaves `ws_ai_ready()`'s static alone; otherwise `ws_ai_ready()`.
- Before (code reading, not reproduced): inside an assistant's request `ws_ai_ready()` could read the assistant's config (`ws_model_config_over()`: on, its application), so a proposal was accepted while Pinegrap AI was off or had no application; on Apply each slice failed three times and "Pinegrap AI did not answer for some of the records." was written.

Related: workspace-bulk-create.md, workspace-record-changes.md
