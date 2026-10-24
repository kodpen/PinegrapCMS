---
name: workspace-canvas-workflow-tables
description: Plan (2026-10-23, not started, answers open): personal-by-default workflow tables, board view, canvases holding them, automations on the scheduled-actions engine
---

## What is asked? (owner, 2026-10-23)
- Canvases holding workflow tables that turn into workflows; a table may be purely personal, with no required link to a channel, task or record.

## What is there today?
- No canvas or table record. Notes model "personal": owner plus shares (`ws_note_access()`), nothing for admins by rank, no webhook. A channel-less task is seen by board holders and leads (`ws_can_see_task()`).
- Grids: GFM tables (`ws_table_cells()`, `ws_calc_grid()`, 200 x 26), `openTable()`, xlsx cells. Board tab: assets/js/workspace_board_channel.js (drag, keyboard, `window.PGWsKit`).
- Engine (includes/workspace/scheduled.php): steps in `ws_scheduled_do()`, creator's current rights, `note_id` ties one to a note. Staff only (`ws_can_schedule()`), each sees all; no API for actions.

## What is proposed? (not approved yet)
- Table: typed columns (text, number, money in minor units, date, person (team only), status (done flag), checkbox, record link via `ws_refs_resolve()`, formula via `ws_calc_evaluate()`); cells JSON in MEDIUMTEXT, revision (`base`, 409 `changed_meanwhile`). Owner-only; shares to people or a channel card. Limits: 50 columns, 5000 rows (paged), 500 tables each.
- Canvas: up to 300 items (table, note, task, text, arrow); pointer events, SVG arrows, an outline list for phones, screen readers; items show only what the reader may open.
- Workflow: rules on the scheduled-actions engine, never a second one: row events kept by `ws_event()`, new steps `add_row`, `set_cell` in `ws_scheduled_do()`. Workflow mode: rows are steps with "depends on"; "Run as tasks" links them with `ws_task_links_set()` (cycle-safe), task ids kept in the rows.
- API in each slice (`ws_openapi_objects()`, `dry_run` with a `*_check()`), MCP tools except own-words routes. `workspace.wtable.*` webhooks (with `channel_id`) only for tables on a public channel card.

## Slices
1. S1 Scheduled actions API, read and stop (no schema): below.
2. S2 Create, change, resume, run (no schema): new `ws_scheduled_save_check()` out of `ws_scheduled_save()`, rehearsal `ws_scheduled_preview()`; `ws_api_own_words_only()`, `'mcp' => false`: they act in the person's name (workspace/workspace-api-callers.md).
3. T1 Tables (schema, ask first): new `ws_wtables`, `ws_wtable_columns`, `ws_wtable_rows`, `ws_wtable_shares`, includes/workspace/wtables.php, `workspace_tables.php`, API, sync key.
4. T2 Board view by a status column (no schema past T1).
5. T3 Canvas (schema, ask first): new `ws_canvases`, `ws_canvas_items`, `workspace_canvas.php`.
6. T4 Table automations (schema, ask first: new `ws_scheduled_actions.wtable_id`); who: question 2.
7. T5 Workflow mode to tasks (no schema past T1).
8. T6 CSV and note-table import, JSON starter tables: contact pipeline (CRM seam), checklist (no schema past T1).
- Schema: `upgrade_2026_11_0_<topic>()` at the next free Workspace label, tables in `get_tables()`.

## Open questions (recommendation)
1. T1 and T3 tables → T1 now, T3 once T1, T2 are in use.
2. Role-3 team members' automations: (a) staff only, (b) own tables, steps in their reach → b.
3. Canvas (none in assets/lib): (a) vanilla pointer events and SVG, (b) a vendored one → a.
4. Task and row status: (a) task to row, (b) both ways, (c) copy once → a.
5. Personal tables for administrators: (a) never, like notes, (b) board holders → a.

## Where to start?
- S1: new `GET /workspace/scheduled-actions` (and `/{id}`), `POST .../{id}/pause`, `/cancel` (keys and MCP too, like scheduled messages); kind action only (`ws_scheduled_action()`); new `ws_scheduled_set_status_check()`, `api_dry_run_stop()`, `ws_scheduled_set_status()`; channel names only where `ws_can_read_channel()`. Tests: tests/workspace_api_test.php. Each slice: a memory file in workspace/.
