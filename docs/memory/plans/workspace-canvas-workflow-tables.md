---
name: workspace-canvas-workflow-tables
description: Plan (2026-10-23, not started, answers open): workflow tables, board view, Workspace canvas, automations; owns the scheduled-actions API
---

## What is asked?
- Workspace canvases of workflow tables that become workflows; also personal, unlinked.

## What is proposed? (not approved)
- Workflow table: columns text, number, money (minor units), date, person (team), status, checkbox, record link (`ws_refs_resolve()`), formula (`ws_calc_evaluate()`); JSON cells (MEDIUMTEXT), PHP filter/sort (no MySQL JSON functions: MariaDB, old MySQL); 409 `changed_meanwhile`. Owner-only like notes (`ws_note_access()`); shares to people or a channel card. 50 columns, 5000 rows, 500 tables each.
- Workspace canvas: max 300 items (table, note, task, text, arrow), outline for phones, screen readers, each item only to its readers.
- Table automation: a scheduled action with `wtable_id`; row events via `ws_event()`; new steps `add_row`, `set_cell` in `ws_scheduled_do()` (no new `kind` or engine). Workflow mode: rows become tasks linked by `ws_task_links_set()` (`blocked_by`, cycle-safe).
- API per slice (`ws_openapi_objects()`, dry_run via `*_check()`), MCP tools bar own-words routes; `workspace.wtable.*` webhooks (`channel_id`) only on a public channel card.
- Shares `ws_notify()`, unshares `ws_inbox_reach_lost()`/`_regained()` (channel-only); `ws_inbox` has no table/canvas column (kind-only line or schema). `ws_sync` key, `ws_palette_groups()` group; deletes: bin or versions (notes keep 30). Person/contact cells (JSON, no SQL re-point) must reach `pg_privacy_export_find()` and erasure (plans/privacy-data-pack-audit-log.md step 4).
- CRM pipeline later = shared table with status, contact link columns (plans/workspace-crm-seams.md); tags in rows (`wtable_row`) wait for its step 9 `ws_refs.source_type` widening.
- Candidates: `ws_templates` starters, xlsx export (includes/fn/office.php), "my tables" strip on /workspace/my-day.

## Slices
1. S1 Scheduled actions API (staff-only `ws_can_schedule()`, all see all): list, get, pause, cancel, after #1352 (merged; changed includes/workspace/watch.php).
2. S2 Create, edit, resume (question 2): new `ws_scheduled_save_check()` from `ws_scheduled_save()`, `ws_scheduled_preview()`; S2b change card after the MCP plan's slice 3 propose route.
3. T1 Tables: new `ws_wtables`, `ws_wtable_columns`, `ws_wtable_rows`, `ws_wtable_shares`, includes/workspace/wtables.php (`ws_wtable_*`, not `ws_table_*`), workspace_wtables.php, API.
4. T2 Board view (the channel Board tab component) by a status column.
5. T3 Workspace canvas: new `ws_canvases`, `ws_canvas_items`, workspace_canvas.php.
6. T4 Table automations: new `ws_scheduled_actions.wtable_id`.
7. T5 Workflow mode.
8. T6 CSV, note-table import; JSON starters: contact pipeline, checklist.
- Schema (ask first): T1, T3, T4; `upgrade_2026_11_0_<topic>()`, next free Workspace label, `get_tables()`.

## Open questions (recommendation)
1. T1, T3 → T1 now, T3 once T1, T2 are in use.
2. Who may create scheduled actions and table automations (run as their creator): (a) staff on a team devices session only (`'mcp' => false`), (b) a + connected apps post a change card the person applies (S2b), (c) b + role-3 members on own tables, steps in their reach, (d) OAuth saves them paused → b now, c after T4.
3. Which first: (a) table automations (T4), (b) workflow mode (T5), (c) both, b first → c; task status flows task to row.
4. Admins on personal tables: (a) never, like notes, (b) board holders → a. Owner deleted or `ws_profiles.excluded`: automations pause, tables, canvases stay invisible.
5. T3 library (assets/lib: none): (a) vanilla pointer events, SVG, (b) vendored → a.

## Where to start?
- S1: new `GET /workspace/scheduled-actions` (+ `/{id}`), `POST .../{id}/pause`, `/cancel` (keys, MCP); action kind only (`ws_scheduled_action()`); new `ws_scheduled_set_status_check()` → `api_dry_run_stop()` → `ws_scheduled_set_status()`; channel names only where `ws_can_read_channel()`. Test: tests/workspace_api_test.php.
