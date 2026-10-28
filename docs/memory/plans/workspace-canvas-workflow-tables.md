---
name: workspace-canvas-workflow-tables
description: Plan (2026-10-23, S1-S2b #1708 #1858 #1987, T1a-T1c #2745 #2794 #2820): workflow tables, board view, the scheduled-actions API; canvas, automations, workflow mode moved to plans/workspace-flows.md
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
1. S1 Scheduled actions API: list, get, pause, cancel — done, #1708; removed before release with S2 in #2107 (workspace/workspace-scheduled-actions-api.md).
2. S2 Create, edit, resume — done, #1858 (workspace/workspace-scheduled-actions-api.md). S2b change card for connected apps — done, #1987 (workspace/workspace-scheduled-draft-proposals.md).
3. T1 Tables: new `ws_wtables`, `ws_wtable_columns`, `ws_wtable_rows`, `ws_wtable_shares`, includes/workspace/wtables.php (`ws_wtable_*`, not `ws_table_*`), workspace_wtables.php, API. Cut:
   - T1a types and store core — done, #2745 (decided in #2746; workspace/workflow-tables.md): types, cell checks, limits, step 11.0.110, `ws_wtable_access()`, create/rename/delete of tables and columns.
   - T1b rows — done, #2794 (workspace/workflow-table-rows.md): person and record-link cells refused (`not_writable_yet`).
   - T1b2 privacy reader — done, #2808 (workspace/workflow-tables.md): the export and erasure read person and link cells (`pg_privacy_wtable_rows()`); `ws_wtable_row_closed_types()` empty.
   - T1c shares — done, #2820 (workspace/workflow-table-shares.md): person view/edit, channel view, `wtable_shared` bell line; no inbox reach change (no table column in `ws_inbox`); `ws_wtable_flow_gate()` for F6's table boxes.
   - T1d API and MCP (`ws_openapi_objects()`, dry_run through `*_check()`), `workspace.wtable.*` webhooks on a public channel card.
   - T1e screen workspace_wtables.php and its place in `ws_nav_groups()`.
4. T2 Board view (the channel Board tab component) by a status column.
5. T6 CSV, note-table import; JSON starters: contact pipeline, checklist.
- Schema (ask first): T1; `upgrade_2026_11_0_<topic>()`, next free Workspace label, `get_tables()`.

## Which questions are answered?
1. Answered (owner, 2026-10-26, #1708): b, staff on their own team devices session (`'mcp' => false`), connected apps a change card (S2b); an action runs as its creator (`workflow/decisions-made-product.md`).
2. Answered (#2424): administrators see a personal table only through a share, like notes (a). Owner deleted or `ws_profiles.excluded`: tables stay invisible, their flows pause.

## Where to start?
- T1d API and MCP (the shares' `*_check()` in wtable_shares.php), then T1e screen.
