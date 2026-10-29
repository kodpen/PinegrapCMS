---
name: workspace-ref-sources
description: Notes and workflow table rows as ws_refs tag sources (#3135, decision #3138, 11.0.116): VARCHAR source_type, probe, writers with channel 0, per-source access in readers, backfill
---

## What does ws_refs keep?
- One row per distinct tag of a text; `source_type` is one of `ws_ref_source_types()`: message, task, note, wtable_row. Readers group rows with `ws_ref_sources()`, which leaves an unknown source (or the `''` an ENUM kept) out; before #3135 every non-message was read as a task.
- A note keeps the record tokens of its text (`ws_note_ref_tokens()`: record kinds only, sign checked, each once); a row the record-link cells of the table's current link columns whose kind is a record kind (`ws_wtable_row_ref_tokens()`).

## Why a schema step?
- `source_type` was `ENUM('message','task')` (2026.4.4); under the non-strict sql_mode a `'note'` is stored as `''`. Step 11.0.116 (`upgrade_2026_11_0_workspace_ref_sources()`) makes it `VARCHAR(20) NOT NULL DEFAULT 'message'` while `install_column_info()` still reads an enum, else skips; one table copy, no heavy-table entry. Decided in #3138 (`workflow/decisions-made-schema-4.md`): a later source needs no MODIFY; a LIKE scan on every record screen was refused.

## Who writes the new sources?
- `ws_ref_source_write($type, $tags)`: one DELETE and one INSERT per batch, `channel_id` 0 (a note or table never makes a channel watch a record, `ws_watch_channels()`), nothing until `ws_ref_sources_ready()` sees the VARCHAR (files land before the step).
- Notes: `ws_note_save()` (panel, API, forward, meetings, templates, an import's update), `ws_note_claude_deliver()`, `ws_note_from_message()`, a new note of `ws_note_import_write()`; `ws_note_delete()` removes. Rows: `ws_wtable_row_create()` (imports too), `_update()`, `_delete()`; `ws_wtable_delete()` removes its rows' first.

## Who sees what?
- `ws_record_refs()` (record drawer, badge, `GET /workspace/refs`, its MCP tool and resource) and `ws_customer_history_ref_rows()`: `ws_ref_notes_open()` keeps a note only where `ws_note_access()` gives access (shares read once); `ws_ref_rows_open()` a row only where `ws_wtable_access()` reaches its table and the row still links to the record (a link column deleted since, or a cell an erasure emptied, drops out). Administrators get nothing by rank.
- Claude's key: `ws_api_note_reachable()` / `ws_api_wtable_reachable()`, in `ws_api_refs_get()` before the channel gate and in the history (`history_claude`).
- Shown: drawer groups Notes and Tables (workspace.js); the route's `notes` {id, name, excerpt, updated} and `rows` {id, table_id, table, title}; history kinds `note`, `wtable_row`, at = `updated_at` (the card's icons: workspace_customer.js). The file manager's "Where used" still lists messages and tasks only.

## How are older notes and rows added?
- `ws_ref_sources_backfill_run()` from `job.php` on every tick while `ws_enabled()`: 5 s a run, 100 at a time, notes then rows by id, cursor `{source, after}` in config_text `ws_refs_backfill` saved after each batch, `{"done":1}` once through (then one small read a tick). Removing that row starts it again; rewriting is idempotent. Waits for the probe.
- Tests: `tests/workspace_ref_sources_test.php` (tokens, cursor, writers and readers read from source, the step run twice on a fake schema).
