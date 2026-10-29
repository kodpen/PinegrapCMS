---
name: workspace-canvas-workflow-tables
description: Plan (2026-10-23, S1-S2b #1708 #1858 #1987, T1-T2 #2745-#2994): workflow tables, board view, the scheduled-actions API; canvas, automations, workflow mode moved to plans/workspace-flows.md
---

## What is asked?
- Workspace canvases of workflow tables that become workflows; also personal, unlinked.

## What is proposed? (not approved)
- Workflow table: columns text, number, money (minor units), date, person (team), status, checkbox, record link (`ws_refs_resolve()`), formula (`ws_calc_evaluate()`); JSON cells (MEDIUMTEXT), PHP filter/sort (no MySQL JSON functions: MariaDB, old MySQL); 409 `changed_meanwhile`. Owner-only like notes (`ws_note_access()`); shares to people or a channel card. 50 columns, 5000 rows, 500 tables each.
- Workspace canvas (T3), table automations (T4), workflow mode (T5): moved to plans/workspace-flows.md (owner, 2026-10-24: flows are the main use).
- API per slice (`ws_openapi_objects()`, dry_run via `*_check()`), MCP tools bar own-words routes; `workspace.wtable.*` webhooks (`channel_id`) only on a public channel card.
- Shares `ws_notify()`, unshares `ws_inbox_reach_lost()`/`_regained()` (channel-only); `ws_inbox` has no table column (kind-only line or schema). `ws_sync` key, `ws_palette_groups()` group; deletes: bin or versions (notes keep 30). Person/contact cells (JSON, no SQL re-point) must reach `pg_privacy_export_find()` and erasure (core/personal-data-erasure.md).
- CRM pipeline later = shared table with status, contact link columns (plans/workspace-crm-seams.md); tags in rows (`wtable_row`) wait for its step 9 `ws_refs.source_type` widening.
- Candidates: `ws_templates` starters, xlsx export (includes/fn/office.php), "my tables" strip on /workspace/my-day.

## Slices
1. S1, S2, S2b scheduled actions API — done, #1708 #1858 #1987 (workspace/workspace-scheduled-actions-api.md, workspace-scheduled-draft-proposals.md).
2. T1 tables (`ws_wtable_*`, workspace_wtables.php, API) — done: T1a store #2745, T1b rows #2794, T1b2 privacy reader #2808, T1c shares #2820, T1d1/T1d2 API #2890 #2931, T1e screen #2966 (workspace/workflow-tables*.md, workflow-table-*.md).
3. T2 Board view (the channel Board tab component) by a status column — done, #2994 (workspace/workflow-table-board.md).
4. T6 CSV, note-table import; JSON starters: contact pipeline, checklist. Cut:
   - T6a a CSV/xlsx/xls/ods file into a new table (header row → columns, a type guessed per column, confirmed before the write) or appended to a table (columns matched by name), every row through `ws_wtable_row_create()`, refused rows reported with their cell errors — done, #3022 (workspace/workflow-table-import.md). Follow-ups: an import route in the API/MCP; one file reader with a row cap shared with `ws_bulk_file_table()` once #3014 has landed.
   - T6b a note's table block imported the same way — in progress (feature/wtables-t6b-note-import).
   - T6c JSON starters (contact pipeline, checklist) on the New table card; check `ws_templates` first.
- Schema (ask first): T1; `upgrade_2026_11_0_<topic>()`, next free Workspace label, `get_tables()`.

## Which questions are answered?
1. Answered (owner, 2026-10-26, #1708): b, staff on their own team devices session (`'mcp' => false`), connected apps a change card (S2b); an action runs as its creator (`workflow/decisions-made-product.md`).
2. Answered (#2424): administrators see a personal table only through a share, like notes (a). Owner deleted or `ws_profiles.excluded`: tables stay invisible, their flows pause.

## Where to start?
- T6b: a note's table block as a workflow table, through T6a's import (`includes/workspace/wtable_import.php`).
- T6c: JSON starters (contact pipeline, checklist) on the New table card.
